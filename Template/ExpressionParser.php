<?php

namespace Wbengine\Template;

use Wbengine\Template\Exception\TemplateException;

/**
 * Translates a template expression into PHP code. It accepts only a small, fixed
 * grammar, so a template can never run arbitrary PHP: there are no function calls
 * (only a whitelist of modifiers), no assignments and no access to constants.
 *
 *   $name   $a.b   $a['b']   $obj->prop   $obj->method($x, 'y')
 *   'text'  "text"  12  1.5  true  false  null
 *   ! not - * / % + - ~ (concatenation)  < <= > >=  == != === !==  && and || or
 *   ( ... )   $value|modifier   $value|modifier:arg:arg
 *
 * Reading a missing variable, key or property gives null, never a warning.
 */
class ExpressionParser
{
    /** Modifiers and the PHP they compile to. %s is the value, further arguments are appended. */
    const MODIFIERS = array(
        'raw'      => null,
        'nofilter' => null,
        'escape'   => 'Runtime::e',
        'upper'    => 'Runtime::upper',
        'lower'    => 'Runtime::lower',
        'trim'     => 'Runtime::trim',
        'default'  => 'Runtime::dflt',
        'truncate' => 'Runtime::truncate',
        'nl2br'    => 'Runtime::nl2br',
        'json'     => 'Runtime::json',
        'url'      => 'Runtime::url',
        'length'   => 'Runtime::length',
        'count'    => 'Runtime::length',
        'date'     => 'Runtime::date',
        'join'     => 'Runtime::join',
    );

    private $_tokens = array();
    private $_pos = 0;
    private $_source = '';
    private $_line = 1;

    /** Modifiers seen at the top level of the last parsed expression. */
    private $_topModifiers = array();

    /**
     * @param string $source the expression text
     * @param int $line for error messages
     * @return string PHP expression
     */
    public function parse($source, $line = 1)
    {
        $this->_source = (string) $source;
        $this->_line = (int) $line;
        $this->_tokens = $this->_tokenize($this->_source);
        $this->_pos = 0;
        $this->_topModifiers = array();

        $code = $this->_parseExpression();
        if ($this->_pos < count($this->_tokens)) {
            $this->_fail('unexpected "' . $this->_tokens[$this->_pos][1] . '"');
        }
        return $code;
    }

    /**
     * Names of the modifiers applied to the whole value, for example array('escape').
     * Valid after parse().
     */
    public function topModifiers()
    {
        return $this->_topModifiers;
    }

    /**
     * Parse "$item" or "$key => $item" (foreach targets): returns the variable names.
     */
    public function parseTargets($source, $line = 1)
    {
        if (!preg_match('/^\s*\$([A-Za-z_][A-Za-z0-9_]*)\s*(?:=>\s*\$([A-Za-z_][A-Za-z0-9_]*)\s*)?$/', $source, $m)) {
            throw new TemplateException(sprintf('Invalid loop variables "%s" on line %d.', $source, $line));
        }
        return isset($m[2]) ? array($m[1], $m[2]) : array(null, $m[1]);
    }

    private function _fail($message)
    {
        throw new TemplateException(sprintf('Template syntax error on line %d: %s in "%s".', $this->_line, $message, $this->_source));
    }

    // ---------------------------------------------------------------- tokens

    private function _tokenize($s)
    {
        $tokens = array();
        $len = strlen($s);
        $i = 0;
        $prevEnd = -1;

        while ($i < $len) {
            $c = $s[$i];

            if (ctype_space($c)) {
                $i++;
                continue;
            }

            $start = $i;
            if ($c === '$' && $i + 1 < $len && preg_match('/[A-Za-z_]/', $s[$i + 1])) {
                preg_match('/\G\$([A-Za-z_][A-Za-z0-9_]*)/', $s, $m, 0, $i);
                $tokens[] = array('var', $m[1], $start, $prevEnd === $start);
                $i += strlen($m[0]);
            } elseif ($c === "'" || $c === '"') {
                $j = $i + 1;
                $value = '';
                while ($j < $len && $s[$j] !== $c) {
                    if ($s[$j] === '\\' && $j + 1 < $len && ($s[$j + 1] === $c || $s[$j + 1] === '\\')) {
                        $j++;
                    }
                    $value .= $s[$j++];
                }
                if ($j >= $len) {
                    $this->_fail('unterminated string');
                }
                $tokens[] = array('str', $value, $start, false);
                $i = $j + 1;
            } elseif (ctype_digit($c)) {
                preg_match('/\G\d+(?:\.\d+)?/', $s, $m, 0, $i);
                $tokens[] = array('num', $m[0], $start, false);
                $i += strlen($m[0]);
            } elseif (preg_match('/[A-Za-z_]/', $c)) {
                preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $s, $m, 0, $i);
                $tokens[] = array('id', $m[0], $start, $prevEnd === $start);
                $i += strlen($m[0]);
            } else {
                $op = null;
                foreach (array('===', '!==', '->', '==', '!=', '<=', '>=', '&&', '||') as $candidate) {
                    if (substr($s, $i, strlen($candidate)) === $candidate) {
                        $op = $candidate;
                        break;
                    }
                }
                if (null === $op) {
                    if (false === strpos('()[],.:|!<>+-*/%~', $c)) {
                        $this->_fail('unexpected character "' . $c . '"');
                    }
                    $op = $c;
                }
                $tokens[] = array('op', $op, $start, $prevEnd === $start);
                $i += strlen($op);
            }
            $prevEnd = $i;
        }
        return $tokens;
    }

    private function _peek($offset = 0)
    {
        return $this->_tokens[$this->_pos + $offset] ?? null;
    }

    private function _isOp($value, $offset = 0)
    {
        $t = $this->_peek($offset);
        return null !== $t && 'op' === $t[0] && $value === $t[1];
    }

    private function _isWord($word)
    {
        $t = $this->_peek();
        return null !== $t && 'id' === $t[0] && strtolower($t[1]) === $word;
    }

    private function _expectOp($value)
    {
        if (!$this->_isOp($value)) {
            $this->_fail('expected "' . $value . '"');
        }
        $this->_pos++;
    }

    // --------------------------------------------------------------- grammar

    private function _parseExpression()
    {
        return $this->_parseOr();
    }

    private function _parseOr()
    {
        $left = $this->_parseAnd();
        while ($this->_isOp('||') || $this->_isWord('or')) {
            $this->_pos++;
            $left = '(' . $left . ' || ' . $this->_parseAnd() . ')';
        }
        return $left;
    }

    private function _parseAnd()
    {
        $left = $this->_parseEquality();
        while ($this->_isOp('&&') || $this->_isWord('and')) {
            $this->_pos++;
            $left = '(' . $left . ' && ' . $this->_parseEquality() . ')';
        }
        return $left;
    }

    private function _parseEquality()
    {
        $left = $this->_parseRelational();
        while (($t = $this->_peek()) && 'op' === $t[0] && in_array($t[1], array('===', '!==', '==', '!='), true)) {
            $this->_pos++;
            $left = '(' . $left . ' ' . $t[1] . ' ' . $this->_parseRelational() . ')';
        }
        return $left;
    }

    private function _parseRelational()
    {
        $left = $this->_parseAdditive();
        while (($t = $this->_peek()) && 'op' === $t[0] && in_array($t[1], array('<', '<=', '>', '>='), true)) {
            $this->_pos++;
            $left = '(' . $left . ' ' . $t[1] . ' ' . $this->_parseAdditive() . ')';
        }
        return $left;
    }

    private function _parseAdditive()
    {
        $left = $this->_parseMultiplicative();
        while (($t = $this->_peek()) && 'op' === $t[0] && in_array($t[1], array('+', '-', '~'), true)) {
            $this->_pos++;
            $right = $this->_parseMultiplicative();
            $left = ('~' === $t[1])
                ? '(Runtime::s(' . $left . ') . Runtime::s(' . $right . '))'
                : '(Runtime::n(' . $left . ') ' . $t[1] . ' Runtime::n(' . $right . '))';
        }
        return $left;
    }

    private function _parseMultiplicative()
    {
        $left = $this->_parseUnary();
        while (($t = $this->_peek()) && 'op' === $t[0] && in_array($t[1], array('*', '/', '%'), true)) {
            $this->_pos++;
            $right = $this->_parseUnary();
            $left = ('*' === $t[1])
                ? '(Runtime::n(' . $left . ') * Runtime::n(' . $right . '))'
                : 'Runtime::arith(' . var_export($t[1], true) . ', ' . $left . ', ' . $right . ')';
        }
        return $left;
    }

    private function _parseUnary()
    {
        if ($this->_isOp('!') || $this->_isWord('not')) {
            $this->_pos++;
            return '!' . $this->_parseUnary();
        }
        if ($this->_isOp('-')) {
            $this->_pos++;
            return '(-Runtime::n(' . $this->_parseUnary() . '))';
        }
        return $this->_parsePostfix();
    }

    private function _parsePostfix()
    {
        $code = $this->_parsePrimary();

        while (true) {
            $t = $this->_peek();
            if (null === $t || 'op' !== $t[0]) {
                break;
            }

            if ('->' === $t[1]) {
                $this->_pos++;
                $name = $this->_peek();
                if (null === $name || 'id' !== $name[0]) {
                    $this->_fail('expected a property or method name after "->"');
                }
                $this->_pos++;
                if ($this->_isOp('(')) {
                    $code = 'Runtime::call(' . $code . ', ' . var_export($name[1], true) . ', array(' . $this->_parseArguments() . '))';
                } else {
                    $code = 'Runtime::prop(' . $code . ', ' . var_export($name[1], true) . ')';
                }
            } elseif ('.' === $t[1] && $t[3]) {
                // $a.b is an array key, but only when written without spaces.
                $this->_pos++;
                $key = $this->_peek();
                if (null === $key || !in_array($key[0], array('id', 'num'), true)) {
                    $this->_fail('expected a key after "."');
                }
                $this->_pos++;
                $code = 'Runtime::item(' . $code . ', ' . ('num' === $key[0] ? $key[1] : var_export($key[1], true)) . ')';
            } elseif ('[' === $t[1] && $t[3]) {
                $this->_pos++;
                $inner = $this->_parseExpression();
                $this->_expectOp(']');
                $code = 'Runtime::item(' . $code . ', ' . $inner . ')';
            } elseif ('|' === $t[1]) {
                $code = $this->_parseModifier($code);
            } else {
                break;
            }
        }
        return $code;
    }

    private function _parseModifier($value)
    {
        $this->_pos++; // |
        $name = $this->_peek();
        if (null === $name || 'id' !== $name[0]) {
            $this->_fail('expected a modifier name after "|"');
        }
        $this->_pos++;
        $modifier = strtolower($name[1]);
        if (!array_key_exists($modifier, self::MODIFIERS)) {
            $this->_fail('unknown modifier "' . $name[1] . '"');
        }

        $args = array();
        while ($this->_isOp(':')) {
            $this->_pos++;
            $args[] = $this->_parseOperand();
        }
        $this->_topModifiers[] = $modifier;

        if (null === self::MODIFIERS[$modifier]) {
            return $value; // raw and nofilter only switch the automatic escaping off
        }
        return self::MODIFIERS[$modifier] . '(' . implode(', ', array_merge(array($value), $args)) . ')';
    }

    /**
     * A modifier argument: a literal or a variable path, without further operators.
     */
    private function _parseOperand()
    {
        $code = $this->_parsePrimary();
        while (true) {
            $t = $this->_peek();
            if (null !== $t && 'op' === $t[0] && '->' === $t[1]) {
                $this->_pos++;
                $name = $this->_peek();
                if (null === $name || 'id' !== $name[0]) {
                    $this->_fail('expected a property name after "->"');
                }
                $this->_pos++;
                $code = 'Runtime::prop(' . $code . ', ' . var_export($name[1], true) . ')';
            } elseif (null !== $t && 'op' === $t[0] && '.' === $t[1] && $t[3]) {
                $this->_pos++;
                $key = $this->_peek();
                if (null === $key || !in_array($key[0], array('id', 'num'), true)) {
                    $this->_fail('expected a key after "."');
                }
                $this->_pos++;
                $code = 'Runtime::item(' . $code . ', ' . ('num' === $key[0] ? $key[1] : var_export($key[1], true)) . ')';
            } else {
                break;
            }
        }
        return $code;
    }

    private function _parsePrimary()
    {
        $t = $this->_peek();
        if (null === $t) {
            $this->_fail('the expression ends too early');
        }
        $this->_pos++;

        switch ($t[0]) {
            case 'var':
                return '($_v[' . var_export($t[1], true) . '] ?? null)';
            case 'str':
                return var_export($t[1], true);
            case 'num':
                return $t[1];
            case 'id':
                $word = strtolower($t[1]);
                if ('true' === $word || 'false' === $word || 'null' === $word) {
                    return $word;
                }
                $this->_fail('unknown word "' . $t[1] . '" (variables start with $)');
        }

        if ('op' === $t[0] && '(' === $t[1]) {
            $inner = $this->_parseExpression();
            $this->_expectOp(')');
            return '(' . $inner . ')';
        }
        $this->_fail('unexpected "' . $t[1] . '"');
    }

    private function _parseArguments()
    {
        $this->_expectOp('(');
        $args = array();
        if (!$this->_isOp(')')) {
            do {
                $args[] = $this->_parseExpression();
                $more = $this->_isOp(',');
                if ($more) {
                    $this->_pos++;
                }
            } while ($more);
        }
        $this->_expectOp(')');
        return implode(', ', $args);
    }
}
