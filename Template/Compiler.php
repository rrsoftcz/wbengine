<?php

namespace Wbengine\Template;

use Wbengine\Template\Exception\TemplateException;

/**
 * Compiles a template to plain PHP.
 *
 * Syntax (a subset of Smarty, so existing templates keep working):
 *
 *   {$value}  {$a.b}  {$obj->prop}  {$obj->method()}  {$value|modifier:arg}  {'text'|modifier}
 *   {if EXPR} {elseif EXPR} {else} {/if}
 *   {foreach EXPR as $item}  {foreach EXPR as $key => $item}  {foreachelse} {/foreach}
 *   {include 'name.tpl'}     {set $x = EXPR}
 *   {cache KEY ttl=300} ... {/cache}        fragment cache
 *   {literal} ... {/literal}                 text with braces, not interpreted
 *   {* comment *}
 *
 * A "{" that does not start one of these is plain text, so inline CSS and
 * JavaScript need no {literal}.
 *
 * Output is HTML-escaped unless the expression has |raw (or |nofilter), or already
 * ends in a modifier that produces safe output (escape, nl2br, json, url). Escaping
 * can be switched off for a whole engine, for old templates that rely on Smarty's
 * default of not escaping.
 */
class Compiler
{
    /** Bump when the generated code changes, so old compiled files are not reused. */
    const VERSION = 2;

    /** Tags that swallow one line break directly after them. */
    const TRIM_AFTER = array('if', 'elseif', 'else', 'foreach', 'foreachelse', 'close', 'set', 'cache', 'comment');

    /** Modifiers whose result is already safe to print. */
    const SAFE_MODIFIERS = array('raw', 'nofilter', 'escape', 'nl2br', 'json', 'url');

    private $_autoEscape;
    private $_parser;

    private $_source;
    private $_name;
    private $_pos;
    private $_line;
    private $_out;
    private $_stack;
    private $_afterPhp;
    private $_blockId;

    public function __construct($autoEscape = true)
    {
        $this->_autoEscape = (bool) $autoEscape;
        $this->_parser = new ExpressionParser();
    }

    /**
     * @param string $source template text
     * @param string $name for error messages
     * @param string $signature identifies the source version (modification time and size),
     *        stored in the compiled file so a stale one can be detected
     * @return string PHP source of the compiled template
     * @throws TemplateException
     */
    public function compile($source, $name = 'template', $signature = '')
    {
        $this->_source = str_replace("\r\n", "\n", (string) $source);
        $this->_name = (string) $name;
        $this->_pos = 0;
        $this->_line = 1;
        $this->_out = '';
        $this->_stack = array();
        $this->_afterPhp = false;
        $this->_blockId = 0;

        $length = strlen($this->_source);
        while ($this->_pos < $length) {
            $brace = strpos($this->_source, '{', $this->_pos);
            if (false === $brace) {
                $this->_text(substr($this->_source, $this->_pos));
                $this->_pos = $length;
                break;
            }

            $tag = $this->_readTag($brace);
            if (null === $tag) {
                // A brace that does not start a tag is plain text.
                $this->_text(substr($this->_source, $this->_pos, $brace + 1 - $this->_pos));
                $this->_pos = $brace + 1;
                continue;
            }

            $this->_text(substr($this->_source, $this->_pos, $brace - $this->_pos));
            $this->_handle($tag['kind'], $tag['body']);
            if ('literal' !== $tag['kind']) {
                // (the body of a literal block was counted when it was emitted)
                $this->_line += substr_count(substr($this->_source, $brace, $tag['end'] - $brace), "\n");
            }
            $this->_pos = $tag['end'];

            // Like Smarty (and "trim_blocks" in other engines): the line break right after
            // a control tag is dropped, so control tags on their own line leave no empty line.
            if (in_array($tag['kind'], self::TRIM_AFTER, true) && "\n" === ($this->_source[$this->_pos] ?? '')) {
                $this->_pos++;
                $this->_line++;
            }
        }

        if ($this->_stack) {
            $open = end($this->_stack);
            throw new TemplateException(sprintf('Template "%s": the {%s} opened on line %d is never closed.', $this->_name, $open['type'], $open['line']));
        }

        return "<?php\n/* WBengine template: " . str_replace('*/', '* /', $this->_name) . " */\n"
            . "// source-signature: " . preg_replace('/[^0-9:]/', '', (string) $signature) . "\n"
            . "use Wbengine\\Template\\Runtime;\n"
            . "return static function (array &\$_v, \\Wbengine\\Template\\Engine \$_engine): void {\n?>"
            . $this->_out
            . "<?php\n};\n";
    }

    // ------------------------------------------------------------- reading

    /**
     * Decide whether the "{" at $brace starts a tag. Returns kind, body and end offset.
     */
    private function _readTag($brace)
    {
        $rest = substr($this->_source, $brace, 40);

        if (0 === strpos($rest, '{*')) {
            $end = strpos($this->_source, '*}', $brace + 2);
            if (false === $end) {
                $this->_fail('a comment is never closed');
            }
            return array('kind' => 'comment', 'body' => '', 'end' => $end + 2);
        }

        if (preg_match('/^\{literal\}/', $rest)) {
            $start = $brace + 9;
            $end = strpos($this->_source, '{/literal}', $start);
            if (false === $end) {
                $this->_fail('{literal} is never closed');
            }
            return array('kind' => 'literal', 'body' => substr($this->_source, $start, $end - $start), 'end' => $end + 10);
        }

        $kind = null;
        if ('$' === ($rest[1] ?? '') || preg_match('/\G\{([\'"])(?:\\\\.|(?!\\1).)*\\1\s*\|/s', $this->_source, $unused, 0, $brace)) {
            // {$value}, or a quoted text with a modifier: {'/blog/'|lurl}. A "{" followed by a
            // quote and no modifier (a JavaScript object) stays plain text.
            $kind = 'output';
        } elseif (preg_match('/^\{\/(if|foreach|cache)\}/', $rest, $m)) {
            return array('kind' => 'close', 'body' => $m[1], 'end' => $brace + strlen($m[0]));
        } elseif (preg_match('/^\{(else|foreachelse)\}/', $rest, $m)) {
            return array('kind' => $m[1], 'body' => '', 'end' => $brace + strlen($m[0]));
        } elseif (preg_match('/^\{(if|elseif|foreach|include|set|cache|t)(?=[\s(\'"$])/', $rest, $m)) {
            $kind = $m[1];
        }
        if (null === $kind) {
            return null;
        }

        $end = $this->_findTagEnd($brace + 1);
        $inner = substr($this->_source, $brace + 1, $end - $brace - 1);
        if ('output' !== $kind) {
            $inner = ltrim(substr($inner, strlen($kind)));
        }
        return array('kind' => $kind, 'body' => $inner, 'end' => $end + 1);
    }

    /**
     * The offset of the "}" that ends a tag, skipping braces inside quoted strings.
     */
    private function _findTagEnd($from)
    {
        $length = strlen($this->_source);
        $quote = null;
        for ($i = $from; $i < $length; $i++) {
            $c = $this->_source[$i];
            if (null !== $quote) {
                if ('\\' === $c) {
                    $i++;
                } elseif ($c === $quote) {
                    $quote = null;
                }
            } elseif ("'" === $c || '"' === $c) {
                $quote = $c;
            } elseif ('}' === $c) {
                return $i;
            }
        }
        $this->_fail('a tag is never closed');
    }

    // ------------------------------------------------------------ emitting

    private function _text($text)
    {
        if ('' === $text) {
            return;
        }
        $this->_line += substr_count($text, "\n");
        // PHP drops one newline right after a closing PHP tag, so a text that starts with one needs another.
        if ($this->_afterPhp && "\n" === $text[0]) {
            $text = "\n" . $text;
        }
        $this->_out .= str_replace('<?', "<?php echo '<?'; ?>", $text);
        $this->_afterPhp = false;
    }

    private function _php($code)
    {
        $this->_out .= '<?php ' . $code . ' ?>';
        $this->_afterPhp = true;
    }

    private function _echo($expression)
    {
        $this->_out .= '<?= ' . $expression . ' ?>';
        $this->_afterPhp = true;
    }

    // ------------------------------------------------------------ handling

    private function _handle($kind, $body)
    {
        switch ($kind) {
            case 'comment':
                return;

            case 'literal':
                $this->_text($body);
                return;

            case 'output':
                $expression = $this->_parser->parse($body, $this->_line);
                $explicit = array_intersect($this->_parser->topModifiers(), self::SAFE_MODIFIERS);
                $this->_echo(($this->_autoEscape && !$explicit)
                    ? 'Runtime::e(' . $expression . ')'
                    : 'Runtime::s(' . $expression . ')');
                return;

            case 'if':
                $this->_open('if');
                $this->_php('if (' . $this->_parser->parse($body, $this->_line) . '):');
                return;

            case 'elseif':
                $this->_requireOpen('if', 'elseif');
                $this->_markElse('elseif');
                $this->_php('elseif (' . $this->_parser->parse($body, $this->_line) . '):');
                return;

            case 'else':
                $this->_requireOpen('if', 'else');
                $this->_markElse('else');
                $this->_php('else:');
                return;

            case 'foreach':
                $this->_foreach($body);
                return;

            case 'foreachelse':
                $this->_requireOpen('foreach', 'foreachelse');
                $top = &$this->_stack[count($this->_stack) - 1];
                if ($top['else']) {
                    $this->_fail('{foreach} has two {foreachelse}');
                }
                $top['else'] = true;
                $this->_php('endforeach; if ($_n' . $top['id'] . ' === 0):');
                return;

            case 'close':
                $this->_close($body);
                return;

            case 'include':
                $this->_echo('$_engine->partial(' . $this->_parser->parse($body, $this->_line) . ', $_v)');
                return;

            case 'set':
                if (!preg_match('/^\$([A-Za-z_][A-Za-z0-9_]*)\s*=(?!=)\s*(.+)$/s', $body, $m)) {
                    $this->_fail('{set} needs "$name = expression"');
                }
                $this->_php('$_v[' . var_export($m[1], true) . '] = ' . $this->_parser->parse($m[2], $this->_line) . ';');
                return;

            case 'cache':
                $this->_cache($body);
                return;

            case 't':
                $this->_translate($body);
                return;
        }
    }

    /**
     * {t 'key' name=$value count=$n raw}
     *
     * Arguments are separated by spaces, so a value with spaces goes in parentheses:
     * count=($a + 1). "count" selects the plural form; every other name=value becomes a
     * %name% placeholder. The text is escaped like any output, "raw" prints it as HTML
     * (the parameters are then escaped one by one).
     */
    private function _translate($body)
    {
        $terms = $this->_splitTerms($body);
        if (!$terms) {
            $this->_fail('{t} needs a message key');
        }

        $key = $this->_parser->parse(array_shift($terms), $this->_line);
        $params = array();
        $count = 'null';
        $raw = false;

        foreach ($terms as $term) {
            if ('raw' === $term) {
                $raw = true;
            } elseif (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.+)$/s', $term, $m)) {
                $value = $this->_parser->parse($m[2], $this->_line);
                if ('count' === $m[1]) {
                    $count = $value;
                } else {
                    $params[] = var_export($m[1], true) . ' => ' . $value;
                }
            } else {
                $this->_fail('{t} does not understand "' . $term . '", use name=value or raw');
            }
        }

        $call = 'Runtime::t(' . $key . ', array(' . implode(', ', $params) . '), ' . $count . ', ' . ($raw ? 'true' : 'false') . ')';
        $this->_echo($raw ? 'Runtime::s(' . $call . ')' : 'Runtime::e(' . $call . ')');
    }

    /**
     * Split on whitespace that is outside quotes, parentheses and brackets.
     */
    private function _splitTerms($body)
    {
        $terms = array();
        $current = '';
        $depth = 0;
        $quote = null;
        $length = strlen($body);

        for ($i = 0; $i < $length; $i++) {
            $c = $body[$i];
            if (null !== $quote) {
                $current .= $c;
                if ('\\' === $c && $i + 1 < $length) {
                    $current .= $body[++$i];
                } elseif ($c === $quote) {
                    $quote = null;
                }
            } elseif ("'" === $c || '"' === $c) {
                $quote = $c;
                $current .= $c;
            } elseif ('(' === $c || '[' === $c) {
                $depth++;
                $current .= $c;
            } elseif (')' === $c || ']' === $c) {
                $depth--;
                $current .= $c;
            } elseif (ctype_space($c) && 0 === $depth) {
                if ('' !== $current) {
                    $terms[] = $current;
                    $current = '';
                }
            } else {
                $current .= $c;
            }
        }
        if (null !== $quote || 0 !== $depth) {
            $this->_fail('unbalanced quotes or parentheses in {t}');
        }
        if ('' !== $current) {
            $terms[] = $current;
        }
        return $terms;
    }

    private function _foreach($body)
    {
        if (!preg_match('/^(.*?)\s+as\s+(\$[^\s].*)$/s', $body, $m)) {
            $this->_fail('{foreach} needs "expression as $item"');
        }
        list($keyVar, $itemVar) = $this->_parser->parseTargets($m[2], $this->_line);
        $source = $this->_parser->parse($m[1], $this->_line);

        $id = ++$this->_blockId;
        $this->_stack[] = array('type' => 'foreach', 'line' => $this->_line, 'else' => false, 'id' => $id);

        $assign = (null !== $keyVar ? '$_v[' . var_export($keyVar, true) . '] = $_k' . $id . '; ' : '')
            . '$_v[' . var_export($itemVar, true) . '] = $_i' . $id . ';';
        $this->_php('$_n' . $id . ' = 0; foreach (Runtime::iter(' . $source . ') as $_k' . $id . ' => $_i' . $id . '): $_n' . $id . '++; ' . $assign);
    }

    private function _cache($body)
    {
        $ttl = 'null';
        $scope = 'public';
        $seen = [];
        while (preg_match('/^(.*?)\s+(ttl|scope)\s*=\s*(\d+|\x27[^\x27]*\x27|"[^"]*"|[A-Za-z]+)\s*$/s', $body, $m)) {
            $body = $m[1];
            if (isset($seen[$m[2]])) {
                $this->_fail('duplicate cache option ' . $m[2]);
            }
            $seen[$m[2]] = true;
            if ('ttl' === $m[2]) {
                if (!ctype_digit($m[3])) {
                    $this->_fail('cache ttl must be a non-negative integer');
                }
                $ttl = (string) (int) $m[3];
            } else {
                $scope = trim($m[3], "\x27\"");
                if (!in_array($scope, ['public', 'user'], true)) {
                    $this->_fail('cache scope must be public or user');
                }
            }
        }
        $key = $this->_parser->parse($body, $this->_line);

        $this->_stack[] = array('type' => 'cache', 'line' => $this->_line, 'else' => false, 'scope' => $scope);
        // The echo and the closure stay open until the matching {/cache} closes them.
        $this->_out .= '<?= $_engine->cache(' . $key . ', ' . $ttl . ', function () use (&$_v, $_engine) { ob_start(); ?>';
        $this->_afterPhp = true;
    }

    private function _close($type)
    {
        $top = end($this->_stack);
        if (false === $top || $top['type'] !== $type) {
            $this->_fail('{/' . $type . '} without a matching opening tag');
        }
        array_pop($this->_stack);

        switch ($type) {
            case 'if':
                $this->_php('endif;');
                break;
            case 'foreach':
                $this->_php($top['else'] ? 'endif;' : 'endforeach;');
                break;
            case 'cache':
                $this->_php('return ob_get_clean(); }, ' . var_export($top['scope'], true) . ')');
                break;
        }
    }

    private function _open($type)
    {
        $this->_stack[] = array('type' => $type, 'line' => $this->_line, 'else' => false);
    }

    private function _requireOpen($type, $tag)
    {
        $top = end($this->_stack);
        if (false === $top || $top['type'] !== $type) {
            $this->_fail('{' . $tag . '} outside of {' . $type . '}');
        }
    }

    private function _markElse($tag)
    {
        $top = &$this->_stack[count($this->_stack) - 1];
        if ($top['else']) {
            $this->_fail('{' . $tag . '} after {else}');
        }
        if ('else' === $tag) {
            $top['else'] = true;
        }
    }

    private function _fail($message)
    {
        throw new TemplateException(sprintf('Template "%s" line %d: %s.', $this->_name, $this->_line, $message));
    }
}
