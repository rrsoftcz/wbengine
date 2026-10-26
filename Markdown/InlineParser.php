<?php
namespace Wbengine\Markdown;

/** Inline tokenizer with delimiter runs. It emits nodes, never HTML fragments. */
final class InlineParser
{
    private readonly Budget $budget;
    public function __construct(private readonly array $references = [], private readonly int $maxDepth = 64, private readonly int $maxWork = 8000000, ?Budget $budget = null)
    {
        $this->budget = $budget ?? new Budget($maxWork, 100000);
    }

    /** @return list<Node> */
    public function parse(string $source, int $depth = 0, bool $insideLink = false): array
    {
        if ($depth > $this->maxDepth) { throw new \LengthException('Markdown inline nesting limit exceeded'); }
        $tokens = [];
        $length = strlen($source);
        for ($i = 0; $i < $length;) {
            $this->tick();
            $char = $source[$i];
            $rest = $char === '&' ? substr($source, $i, 40) : '';
            if ($char === '\\' && isset($source[$i + 1]) && preg_match('/[!"#$%&\x27()*+,\-.\/:;<=>?@\[\\\\\]^_`{|}~]/', $source[$i + 1])) {
                $tokens[] = $this->budget->node('text', $source[$i + 1]); $i += 2; continue;
            }
            if ($char === '\\' && ($source[$i + 1] ?? '') === "\n") { $tokens[] = $this->budget->node('hardbreak'); $i += 2; continue; }
            if ($char === ' ') {
                $spaces = strspn($source, ' ', $i);
                if (($source[$i + $spaces] ?? '') === "\n") { if ($spaces >= 2) { $tokens[] = $this->budget->node('hardbreak'); $i++; } $i += $spaces; continue; }
                $tokens[] = $this->budget->node('text', str_repeat(' ', $spaces)); $i += $spaces; continue;
            }
            if ($char === "\n") { $tokens[] = $this->budget->node('softbreak'); $i++; continue; }
            if ($char === '&' && preg_match('/^&(?:#[xX][0-9a-fA-F]{1,6}|#[0-9]{1,7}|[a-zA-Z][a-zA-Z0-9]{1,31});/', $rest, $entity)) {
                $tokens[] = $this->budget->node('text', self::decode($entity[0])); $i += strlen($entity[0]); continue;
            }
            if ($char === '`') {
                $n = strspn($source, '`', $i); $run = [str_repeat('`', $n)]; $offset = $i + $n; $end = false;
                while (($found = strpos($source, $run[0], $offset)) !== false) {
                    $this->budget->work($found - $offset + $n);
                    if (($source[$found - 1] ?? '') !== '`' && ($source[$found + $n] ?? '') !== '`') { $end = $found; break; }
                    $offset = $found + $n;
                }
                if ($end !== false) {
                    $text = str_replace("\n", ' ', substr($source, $i + $n, $end - $i - $n));
                    if (str_starts_with($text, ' ') && str_ends_with($text, ' ') && trim($text, ' ') !== '') { $text = substr($text, 1, -1); }
                    $tokens[] = $this->budget->node('code', $text); $i = $end + $n; continue;
                }
                $this->budget->work($length - $offset);
                $tokens[] = $this->budget->node('text', $run[0]); $i += $n; continue;
            }
            if ($char === '<') {
                if (!$insideLink && preg_match('/\\G<([a-zA-Z][a-zA-Z0-9+.-]{1,31}:[^<>\x00-\x20]*)>/', $source, $auto, 0, $i)) {
                    $tokens[] = $this->budget->node('link', children: [$this->budget->node('text', $auto[1])], attributes: ['destination' => $auto[1]]); $i += strlen($auto[0]); continue;
                }
                if (!$insideLink && preg_match('/\\G<([a-zA-Z0-9.!#$%&\x27*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?)>/', $source, $auto, 0, $i)) {
                    $tokens[] = $this->budget->node('link', children: [$this->budget->node('text', $auto[1])], attributes: ['destination' => 'mailto:' . $auto[1]]); $i += strlen($auto[0]); continue;
                }
                if (($html = Syntax::html($source, $i)) !== null) {
                    $this->budget->work(strlen($html));
                    $tokens[] = $this->budget->node('html', $html); $i += strlen($html); continue;
                }
                $this->budget->work($length - $i);
            }
            $image = $char === '!' && ($source[$i + 1] ?? '') === '[';
            if (($char === '[' && !$insideLink) || $image) {
                $open = $i + ($image ? 1 : 0); $close = $this->closingBracket($source, $open);
                if ($close !== null) {
                    $label = substr($source, $open + 1, $close - $open - 1); $end = $close + 1; $attributes = null;
                    if (($source[$end] ?? '') === '(') {
                        $target = $this->destination($source, $end);
                        if ($target !== null) { [$attributes, $end] = $target; }
                    }
                    if ($attributes === null) {
                        $key = $label;
                        if (($source[$end] ?? '') === '[' && ($refEnd = $this->closingBracket($source, $end)) !== null) {
                            $key = substr($source, $end + 1, $refEnd - $end - 1) ?: $label; $end = $refEnd + 1;
                        }
                        $attributes = mb_strlen($key, 'UTF-8') <= 999 && preg_match('/^(?:\\\\.|[^\[\]\\\\])*$/us', $key) ? ($this->references[self::label($key)] ?? null) : null;
                    }
                    if ($attributes !== null) {
                        $children = $this->parse($label, $depth + 1, false);
                        if (!$image && $this->hasLink($children)) { $tokens[] = $this->budget->node('text', '['); $i++; continue; }
                        // Links cannot contain another link, including a nested autolink.
                        $tokens[] = $this->budget->node($image ? 'image' : 'link', children: $children, attributes: $attributes); $i = $end; continue;
                    }
                }
            }
            if ($char === '*' || $char === '_' || $char === '~') {
                $n = strspn($source, $char, $i); $beforeStart = max(0, $i - 4);
                $before = $i === 0 ? ' ' : mb_substr(mb_scrub(substr($source, $beforeStart, $i - $beforeStart), 'UTF-8'), -1, 1, 'UTF-8');
                $after = mb_substr(substr($source, $i + $n, 4), 0, 1, 'UTF-8') ?: ' ';
                $beforeSpace = preg_match('/\s/u', $before) === 1; $afterSpace = preg_match('/\s/u', $after) === 1;
                $beforePunctuation = preg_match('/[\p{P}\p{S}]/u', $before) === 1; $afterPunctuation = preg_match('/[\p{P}\p{S}]/u', $after) === 1;
                $left = !$afterSpace && (!$afterPunctuation || $beforeSpace || $beforePunctuation);
                $right = !$beforeSpace && (!$beforePunctuation || $afterSpace || $afterPunctuation);
                if ($char === '_') { $canOpen = $left && (!$right || $beforePunctuation); $canClose = $right && (!$left || $afterPunctuation); }
                else { $canOpen = $left; $canClose = $right; }
                if ($char === '~' && $n !== 2) { $tokens[] = $this->budget->node('text', str_repeat($char, $n)); }
                else { $tokens[] = ['char' => $char, 'count' => $n, 'open' => $canOpen, 'close' => $canClose]; }
                $i += $n; continue;
            }
            $n = strcspn($source, "\\\n&`<[]!*_~ ", $i);
            if ($n === 0) { $n = 1; }
            $tokens[] = $this->budget->node('text', substr($source, $i, $n)); $i += $n;
        }
        for ($close = 0; $close < count($tokens); $close++) {
            if (!is_array($tokens[$close]) || !$tokens[$close]['close']) { continue; }
            for ($open = $close - 1; $open >= 0; $open--) {
                $this->tick(); $o = $tokens[$open]; $c = $tokens[$close];
                if (!is_array($o) || !$o['open'] || $o['char'] !== $c['char']) { continue; }
                if (($o['close'] || $c['open']) && ($o['count'] + $c['count']) % 3 === 0 && ($o['count'] % 3 !== 0 || $c['count'] % 3 !== 0)) { continue; }
                $used = min($o['count'], $c['count']) >= 2 ? 2 : 1;
                $children = $this->nodes(array_slice($tokens, $open + 1, $close - $open - 1));
                $node = $this->budget->node($o['char'] === '~' ? 'strikethrough' : ($used === 2 ? 'strong' : 'emphasis'), children: $children);
                $replacement = []; $o['count'] -= $used; $c['count'] -= $used;
                if ($o['count'] > 0) { $replacement[] = $o; }
                $replacement[] = $node;
                if ($c['count'] > 0) { $replacement[] = $c; }
                $this->budget->work(count($tokens) - $open);
                array_splice($tokens, $open, $close - $open + 1, $replacement);
                $close = $open - 1; break;
            }
        }
        return $this->nodes($tokens);
    }

    private function hasLink(array $nodes, int $depth = 0): bool
    {
        if ($depth > $this->maxDepth) { throw new \LengthException('Markdown inline nesting limit exceeded'); }
        foreach ($nodes as $node) {
            $this->budget->work();
            if ($node->type === 'link' || ($node->type !== 'image' && $this->hasLink($node->children, $depth + 1))) { return true; }
        }
        return false;
    }

    private function nodes(array $tokens): array
    {
        return array_map(fn($t) => $t instanceof Node ? $t : $this->budget->node('text', str_repeat($t['char'], $t['count'])), $tokens);
    }

    private function closingBracket(string $text, int $open): ?int
    {
        $nesting = 0;
        for ($i = $open + 1, $n = strlen($text); $i < $n; $i++) {
            $this->tick();
            if ($text[$i] === '\\') { $i++; continue; }
            if ($text[$i] === '`') {
                $run = strspn($text, '`', $i); $offset = $i + $run;
                while (($found = strpos($text, str_repeat('`', $run), $offset)) !== false) {
                    $this->budget->work($found - $offset + $run);
                    if (($text[$found - 1] ?? '') !== '`' && ($text[$found + $run] ?? '') !== '`') { $i = $found + $run - 1; continue 2; }
                    $offset = $found + $run;
                }
                $this->budget->work(strlen($text) - $offset);
            }
            if ($text[$i] === '<') {
                if (preg_match('/\\G<[A-Za-z][A-Za-z0-9+.-]{1,31}:[^<>\\x00-\\x20]*>/', $text, $auto, 0, $i)) { $i += strlen($auto[0]) - 1; continue; }
                if (($html = Syntax::html($text, $i)) !== null) { $this->budget->work(strlen($html)); $i += strlen($html) - 1; continue; }
                $this->budget->work(strlen($text) - $i);
            }
            if ($text[$i] === '[') { $nesting++; }
            if ($text[$i] === ']' && $nesting-- === 0) { return $i; }
        }
        return null;
    }

    private function destination(string $text, int $start): ?array
    {
        $i = $start + 1; $n = strlen($text);
        while ($i < $n && str_contains(" \t\n", $text[$i])) { $i++; }
        $destination = ''; $nesting = 0;
        if (($text[$i] ?? '') === '<') {
            $i++;
            while ($i < $n && $text[$i] !== '>') {
                $this->tick();
                if ($text[$i] === "\n" || $text[$i] === '<') { return null; }
                if ($text[$i] === '\\' && isset($text[$i + 1]) && Syntax::escapable($text[$i + 1])) { $destination .= $text[$i++]; }
                $destination .= $text[$i++];
            }
            if (($text[$i] ?? '') !== '>') { return null; }
            $i++;
        } else {
            while ($i < $n) {
                $this->tick(); $char = $text[$i];
                if ($char === '\\' && isset($text[$i + 1]) && Syntax::escapable($text[$i + 1])) { $destination .= $char . $text[$i + 1]; $i += 2; continue; }
                if ($char === '(') { if (++$nesting > $this->maxDepth) { return null; } }
                elseif ($char === ')') { if ($nesting === 0) { break; } $nesting--; }
                elseif (str_contains(" \t\n\r\f", $char)) { break; }
                elseif ($char === '<' || ord($char) < 32) { return null; }
                $destination .= $char; $i++;
            }
            if ($nesting !== 0) { return null; }
        }
        $space = $i;
        while ($i < $n && str_contains(" \t\n", $text[$i])) { $i++; }
        $a = ['destination' => self::unescape($destination)];
        if ($i > $space && in_array($text[$i] ?? '', ['"', "'", '('], true)) {
            $quote = $text[$i] === '(' ? ')' : $text[$i]; $title = ''; $i++;
            while ($i < $n && $text[$i] !== $quote) {
                $this->tick();
                if ($quote === ')' && $text[$i] === '(') { return null; }
                if ($text[$i] === '\\' && isset($text[$i + 1])) { $title .= $text[$i++]; }
                $title .= $text[$i++];
            }
            if (($text[$i] ?? '') !== $quote) { return null; }
            $a['title'] = self::unescape($title); $i++;
            while ($i < $n && str_contains(" \t\n", $text[$i])) { $i++; }
        }
        return ($text[$i] ?? '') === ')' ? [$a, $i + 1] : null;
    }

    public static function label(string $text): string
    {
        return mb_convert_case(preg_replace('/\s+/u', ' ', trim(Syntax::utf8($text))), MB_CASE_FOLD, 'UTF-8');
    }

    public static function unescape(string $text): string
    {
        return self::decode(preg_replace('/\\\\([!"#$%&\x27()*+,\-.\/:;<=>?@\[\\\\\]^_`{|}~])/', '$1', $text));
    }

    public static function decode(string $text): string
    {
        return preg_replace_callback('/&(?:#[xX][0-9a-fA-F]{1,6}|#[0-9]{1,7}|[a-zA-Z][a-zA-Z0-9]{1,31});/', static function ($m) {
            if (preg_match('/^&#([xX][0-9a-fA-F]+|[0-9]+);$/', $m[0], $number)) {
                $point = strtolower($number[1][0]) === 'x' ? hexdec(substr($number[1], 1)) : (int) $number[1];
                if ($point === 0 || $point > 0x10ffff || ($point >= 0xd800 && $point <= 0xdfff)) { return "\u{fffd}"; }
                return mb_chr($point, 'UTF-8');
            }
            return html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }, $text);
    }

    private function tick(): void
    {
        $this->budget->work();
    }
}
