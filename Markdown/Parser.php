<?php
namespace Wbengine\Markdown;

/** Stateful per-parse block parser. Reusable facade, no static document state. */
final class Parser
{
    private array $references = [];
    private Budget $budget;

    public function __construct(private readonly int $maxBytes = 1048576, private readonly int $maxDepth = 64, private readonly int $maxWork = 8000000, private readonly int $maxNodes = 100000)
    {
        if ($maxBytes < 1 || $maxDepth < 1 || $maxWork < 1 || $maxNodes < 1) { throw new \InvalidArgumentException('Markdown limits must be positive'); }
    }

    public function parse(string $source): Node
    {
        if (strlen($source) > $this->maxBytes) { throw new \LengthException('Markdown input size limit exceeded'); }
        $this->references = []; $this->budget = new Budget($this->maxWork, $this->maxNodes);
        $source = Syntax::utf8(str_replace(["\r\n", "\r", "\0"], ["\n", "\n", "\u{fffd}"], $source));
        $lines = explode("\n", $source);
        if (end($lines) === '') { array_pop($lines); }
        $lines = array_map($this->expandIndent(...), $lines);
        $tree = $this->budget->node('document', children: $this->blocks($lines, 0));
        $tree = $this->inlines($tree, new InlineParser($this->references, $this->maxDepth, $this->maxWork, $this->budget));
        $this->validateDepth($tree, 0);
        return $tree;
    }

    private function expandIndent(string $line): string
    {
        preg_match('/^(?:[ \t>]|[-+*](?=[ \t])|[0-9]{1,9}[.)](?=[ \t]))*/', $line, $match); $indent = ''; $column = 0;
        foreach (str_split($match[0]) as $char) { $n = $char === "\t" ? 4 - $column % 4 : 1; $indent .= $char === "\t" ? str_repeat(' ', $n) : $char; $column += $n; }
        return $indent . substr($line, strlen($match[0]));
    }

    private function blocks(array $lines, int $depth): array
    {
        if ($depth > $this->maxDepth) { throw new \LengthException('Markdown block nesting limit exceeded'); }
        $nodes = []; $n = count($lines);
        for ($i = 0; $i < $n;) {
            $this->tick(); $line = $lines[$i];
            if (trim($line) === '') { $i++; continue; }
            if (($definition = $this->reference($lines, $i)) !== null) {
                [$key, $attributes, $consumed] = $definition;
                $this->references[$key] ??= $attributes;
                $i += $consumed; continue;
            }
            if (preg_match('/^ {0,3}(`{3,}|~{3,})(.*)$/', $line, $fence) && ($fence[1][0] !== '`' || !str_contains($fence[2], '`'))) {
                $indent = strspn($line, ' '); $marker = $fence[1][0]; $count = strlen($fence[1]); $body = []; $i++;
                while ($i < $n && !preg_match('/^ {0,3}' . preg_quote($marker, '/') . '{' . $count . ',}[ \t]*$/', $lines[$i])) {
                    $this->tick(); $body[] = substr($lines[$i], min($indent, strspn($lines[$i], ' '))); $i++;
                }
                if ($i < $n) { $i++; }
                $info = preg_split('/\s+/', trim(InlineParser::unescape($fence[2])))[0];
                $nodes[] = $this->budget->node('code_block', implode("\n", $body) . ($body ? "\n" : ''), attributes: ['info' => $info]); continue;
            }
            if (preg_match('/^ {0,3}(#{1,6})(?:[ \t]+(.*)|$)/', $line, $heading)) {
                $text = preg_replace('/(?:^[#]+[ \t]*$|[ \t]+#+[ \t]*$)/', '', $heading[2] ?? '');
                $nodes[] = $this->budget->node('heading', trim($text), attributes: ['level' => strlen($heading[1])]); $i++; continue;
            }
            if ($this->thematic($line)) { $nodes[] = $this->budget->node('thematic_break'); $i++; continue; }
            if (preg_match('/^ {0,3}>[ \t]?(.*)$/', $line, $quote)) {
                $body = []; $paragraph = false;
                while ($i < $n) {
                    $this->tick();
                    if (preg_match('/^ {0,3}>[ \t]?(.*)$/', $lines[$i], $quote)) { $body[] = $quote[1]; $paragraph = $this->lazyParagraph($quote[1]); $i++; }
                    elseif (trim($lines[$i]) !== '' && $paragraph && !$this->interrupt($lines[$i])) { $lazy = $lines[$i++]; $body[] = preg_match('/^ {0,3}(=+|-+)[ \t]*$/', $lazy) ? '\\' . $lazy : $lazy; }
                    else { break; }
                }
                $nodes[] = $this->budget->node('blockquote', children: $this->blocks($body, $depth + 1)); continue;
            }
            if (($marker = $this->listMarker($line)) !== null) {
                $ordered = $marker['ordered']; $start = $marker['start']; $delimiter = $marker['delimiter']; $contentLimit = $marker['contentIndent']; $items = []; $loose = false;
                while ($i < $n && ($current = $this->listMarker($lines[$i])) !== null && $current['indent'] < $contentLimit && !$this->thematic($lines[$i]) && $current['delimiter'] === $delimiter && $current['ordered'] === $ordered) {
                    $contentIndent = $current['contentIndent']; $body = [$current['body']]; $i++; $blank = false; $fenced = null; $hasNestedBlock = $this->interrupt($current['body']);
                    if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $current['body'], $f)) { $fenced = $f[1][0]; }
                    while ($i < $n) {
                        $this->tick(); $next = $this->listMarker($lines[$i]);
                        if ($next !== null && $next['indent'] < $contentIndent) { if ($blank) { $loose = true; } break; }
                        if (trim($lines[$i]) === '') { $body[] = ''; $blank = true; $i++; continue; }
                        $indent = strspn($lines[$i], ' ');
                        if ($blank && trim($body[0]) === '' && count(array_filter($body, static fn($x) => trim($x) !== '')) === 0) { break; }
                        if ($indent >= $contentIndent) { if ($blank && ($indent === $contentIndent || !$hasNestedBlock) && $fenced === null) { $loose = true; }
                            $continuation = substr($lines[$i], $contentIndent);
                            if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $continuation, $f)) { $fenced = $fenced === null ? $f[1][0] : null; }
                            if ($this->interrupt($continuation)) { $hasNestedBlock = true; }
                            $body[] = $continuation; $blank = false; $i++; continue; }
                        if (!$blank && !$this->interrupt($lines[$i]) && trim(end($body)) !== '') { $body[] = $lines[$i++]; continue; }
                        break;
                    }
                    while ($body && trim(end($body)) === '') { array_pop($body); }
                    $items[] = $this->blocks($body, $depth + 1); $contentLimit = $current['contentIndent'];
                }
                $children = [];
                foreach ($items as $item) {
                    $out = [];
                    foreach ($item as $j => $child) {
                        $literal = $child->literal; $inline = $child->children;
                        if ($j === 0 && $child->type === 'paragraph' && preg_match('/^\[([ \t\n\r\f\v xX])\][ \t\n\r\f\v]+(.*)$/s', $literal, $task)) {
                            $literal = $task[2]; $inline = [$this->budget->node('task', attributes: ['checked' => strtolower($task[1]) === 'x'])];
                        }
                        $out[] = $this->budget->node($child->type, $literal, $inline, $child->attributes + ($child->type === 'paragraph' ? ['tight' => !$loose] : []));
                    }
                    $children[] = $this->budget->node('item', children: $out, attributes: ['block' => $out && ($loose || $out[0]->type !== 'paragraph')]);
                }
                $nodes[] = $this->budget->node('list', children: $children, attributes: ['ordered' => $ordered, 'start' => $start]); continue;
            }
            if (($html = $this->htmlBlock($line)) !== null) {
                $body = [];
                do {
                    $this->tick(); $body[] = $lines[$i++];
                    if ($html !== '' && preg_match($html, end($body))) { break; }
                } while ($i < $n && ($html !== '' || trim($lines[$i]) !== ''));
                $nodes[] = $this->budget->node('html_block', implode("\n", $body) . "\n"); continue;
            }
            if (str_starts_with($line, '    ')) {
                $body = [];
                while ($i < $n && (str_starts_with($lines[$i], '    ') || trim($lines[$i]) === '')) { $this->tick(); $body[] = str_starts_with($lines[$i], '    ') ? substr($lines[$i], 4) : ''; $i++; }
                while ($body && end($body) === '') { array_pop($body); }
                $nodes[] = $this->budget->node('code_block', implode("\n", $body) . "\n"); continue;
            }
            if ($i + 1 < $n && str_contains($line, '|') && ($alignments = $this->tableDelimiter($lines[$i + 1])) !== null && count($this->cells($line)) === count($alignments)) {
                $header = $this->tableRow($line, $alignments, true); $i += 2; $rows = [];
                while ($i < $n && trim($lines[$i]) !== '' && !$this->interrupt($lines[$i])) { $rows[] = $this->tableRow($lines[$i++], $alignments, false); }
                $children = [$this->budget->node('table_head', children: [$header])];
                if ($rows) { $children[] = $this->budget->node('table_body', children: $rows); }
                $nodes[] = $this->budget->node('table', children: $children); continue;
            }
            $paragraph = []; $setext = null;
            while ($i < $n && trim($lines[$i]) !== '') {
                $this->tick();
                if ($paragraph && preg_match('/^ {0,3}(=+|-+)[ \t]*$/', $lines[$i], $underline)) { $setext = $underline[1][0] === '=' ? 1 : 2; $i++; break; }
                if ($paragraph && $this->interrupt($lines[$i])) { break; }
                $paragraph[] = ltrim($lines[$i], ' '); $i++;
            }
            $text = rtrim(implode("\n", $paragraph), " \t");
            $nodes[] = $this->budget->node($setext === null ? 'paragraph' : 'heading', $text, attributes: $setext === null ? [] : ['level' => $setext]);
        }
        return $nodes;
    }

    private function listMarker(string $line): ?array
    {
        if (!preg_match('/^( {0,3})(?:([-+*])|([0-9]{1,9})([.)]))(?:([ \t]+)(.*)|$)/', $line, $m)) { return null; }
        $ordered = ($m[3] ?? '') !== ''; $indent = strlen($m[1]); $markWidth = $ordered ? strlen($m[3]) + 1 : 1;
        $spaces = strlen($m[5] ?? ''); $padding = trim($m[6] ?? '') === '' ? 1 : ($spaces >= 1 && $spaces <= 4 ? $spaces : 1);
        return ['ordered' => $ordered, 'start' => $ordered ? (int) $m[3] : 1, 'delimiter' => $ordered ? $m[4] : $m[2], 'indent' => $indent, 'contentIndent' => $indent + $markWidth + $padding, 'body' => ($spaces > 4 ? str_repeat(' ', $spaces - 1) : '') . ($m[6] ?? '')];
    }

    private function thematic(string $line): bool
    {
        return preg_match('/^ {0,3}(?:(?:\*[ \t]*){3,}|(?:_[ \t]*){3,}|(?:-[ \t]*){3,})$/', $line) === 1;
    }

    private function interrupt(string $line): bool
    {
        $marker = $this->listMarker($line);
        return $this->thematic($line) || $this->htmlBlock($line, true) !== null || preg_match('/^ {0,3}(?:#{1,6}(?:[ \t]|$)|>|`{3,}(?![^`]*`)|~{3,})/', $line) === 1 || ($marker !== null && (!$marker['ordered'] || $marker['start'] === 1) && trim($marker['body']) !== '');
    }

    private function inlines(Node $node, InlineParser $inline): Node
    {
        if (in_array($node->type, ['paragraph', 'heading', 'table_cell'], true)) { return $this->budget->node($node->type, children: [...$node->children, ...$inline->parse($node->literal)], attributes: $node->attributes); }
        return $this->budget->node($node->type, $node->literal, array_map(fn(Node $child) => $this->inlines($child, $inline), $node->children), $node->attributes);
    }

    private function cells(string $line): array
    {
        $line = trim($line);
        if (str_starts_with($line, '|')) { $line = substr($line, 1); }
        if (str_ends_with($line, '|') && !str_ends_with($line, '\\|')) { $line = substr($line, 0, -1); }
        return array_map('trim', preg_split('/(?<!\\\\)\|/', $line));
    }

    private function tableDelimiter(string $line): ?array
    {
        $out = [];
        foreach ($this->cells($line) as $cell) {
            if (!preg_match('/^:?-+:?$/', $cell)) { return null; }
            $out[] = str_starts_with($cell, ':') ? (str_ends_with($cell, ':') ? 'center' : 'left') : (str_ends_with($cell, ':') ? 'right' : '');
        }
        return $out ?: null;
    }

    private function tableRow(string $line, array $alignments, bool $header): Node
    {
        $cells = $this->cells($line); $out = [];
        foreach ($alignments as $i => $align) { $out[] = $this->budget->node('table_cell', str_replace('\\|', '|', $cells[$i] ?? ''), attributes: ['align' => $align, 'header' => $header]); }
        return $this->budget->node('table_row', children: $out);
    }

    private function reference(array $lines, int $start): ?array
    {
        // Reference definitions can span lines, but never a blank line.
        if (!preg_match('/^ {0,3}\[/', $lines[$start])) { return null; }
        
        $parts = [];
        for ($j = $start; $j < count($lines) && trim($lines[$j]) !== '' && count($parts) < 1000; $j++) { $parts[] = $lines[$j]; }
        $this->budget->work(array_sum(array_map('strlen', $parts)) + count($parts));
        $text = implode("\n", $parts);
        if (!preg_match('/^ {0,3}\[((?:\\\\.|[^\[\]\\\\]){1,999})\]:[ \t]*(?:\n[ \t]*)?/u', $text, $m)) { return null; }
        $key = InlineParser::label($m[1]);
        if ($key === '') { return null; }
        $i = strlen($m[0]); $n = strlen($text); $destination = '';
        if (($text[$i] ?? '') === '<') {
            $i++;
            while ($i < $n && $text[$i] !== '>') {
                $this->tick();
                if ($text[$i] === "\n" || $text[$i] === '<') { return null; }
                if ($text[$i] === '\\' && isset($text[$i + 1]) && Syntax::escapable($text[$i + 1])) { $destination .= $text[$i++]; }
                $destination .= $text[$i++];
            }
            if (($text[$i++] ?? '') !== '>') { return null; }
        } else {
            $nesting = 0;
            while ($i < $n && !str_contains(" \t\n", $text[$i])) {
                $this->tick();
                if ($text[$i] === '<' || ord($text[$i]) < 32) { return null; }
                if ($text[$i] === '\\' && isset($text[$i + 1]) && Syntax::escapable($text[$i + 1])) { $destination .= $text[$i++] . $text[$i++]; continue; }
                if ($text[$i] === '(' && ++$nesting > $this->maxDepth) { return null; }
                if ($text[$i] === ')' && --$nesting < 0) { return null; }
                $destination .= $text[$i++];
            }
            if ($destination === '' || $nesting !== 0) { return null; }
        }
        $destinationEnd = $i;
        while ($i < $n && str_contains(" \t", $text[$i])) { $i++; }
        $destinationLineEnd = $i;
        if (($text[$i] ?? '') === "\n") { $i++; while ($i < $n && str_contains(" \t", $text[$i])) { $i++; } }
        $a = ['destination' => InlineParser::unescape($destination)];
        if ($i > $destinationEnd && in_array($text[$i] ?? '', ['"', "'", '('], true)) {
            $titleStart = $i; $quote = $text[$i] === '(' ? ')' : $text[$i]; $i++; $title = '';
            while ($i < $n && $text[$i] !== $quote) {
                $this->tick();
                if ($quote === ')' && $text[$i] === '(') { break; }
                if ($text[$i] === '\\' && isset($text[$i + 1])) { $title .= $text[$i++]; }
                $title .= $text[$i++];
            }
            if (($text[$i] ?? '') !== $quote) {
                if (substr_count(substr($text, $destinationEnd, $titleStart - $destinationEnd), "\n") === 0) { return null; }
                return [$key, $a, substr_count(substr($text, 0, $destinationLineEnd), "\n") + 1];
            }
            $i++; while ($i < $n && str_contains(" \t", $text[$i])) { $i++; }
            if ($i < $n && $text[$i] !== "\n") {
                if (substr_count(substr($text, $destinationEnd, $titleStart - $destinationEnd), "\n") === 0) { return null; }
                return [$key, $a, substr_count(substr($text, 0, $destinationLineEnd), "\n") + 1];
            }
            $a['title'] = InlineParser::unescape($title);
        } else { $i = $destinationLineEnd; }
        if ($i < $n && $text[$i] !== "\n") { return null; }
        return [$key, $a, substr_count(substr($text, 0, $i), "\n") + 1];
    }

    private function lazyParagraph(string $line, int $depth = 0): bool
    {
        $this->budget->work(strlen($line));
        if ($depth > $this->maxDepth) { throw new \LengthException('Markdown lazy container nesting limit exceeded'); }
        while (preg_match('/^ {0,3}> ?(.*)$/', $line, $m)) { $this->budget->work(strlen($line)); $line = $m[1]; }
        if (($m = $this->listMarker($line)) !== null) { return $this->lazyParagraph($m['body'], $depth + 1); }
        return trim($line) !== '' && !str_starts_with($line, '    ') && !$this->interrupt($line);
    }

    private function htmlBlock(string $line, bool $interrupt = false): ?string
    {
        if (preg_match('/^ {0,3}<(script|pre|style|textarea)(?:[ \t>]|$)/i', $line, $m)) { return '~</' . strtolower($m[1]) . '>~i'; }
        if (preg_match('/^ {0,3}<!--/', $line)) { return '/-->/'; }
        if (preg_match('/^ {0,3}<\?/', $line)) { return '/\?>/'; }
        if (preg_match('/^ {0,3}<![A-Z]/', $line)) { return '/>/'; }
        if (preg_match('/^ {0,3}<!\[CDATA\[/', $line)) { return '/\]\]>/'; }
        $tags = 'address|article|aside|base|basefont|blockquote|body|caption|center|col|colgroup|dd|details|dialog|dir|div|dl|dt|fieldset|figcaption|figure|footer|form|frame|frameset|h[1-6]|head|header|hr|html|iframe|legend|li|link|main|menu|menuitem|nav|noframes|ol|optgroup|option|p|param|search|section|summary|table|tbody|td|tfoot|th|thead|title|tr|track|ul';
        if (preg_match('~^ {0,3}</?(?:' . $tags . ')(?:[ \t/>]|$)~i', $line)) { return ''; }
        if (!$interrupt && Syntax::completeTag($line)) { return ''; }
        return null;
    }

    private function validateDepth(Node $node, int $depth): void
    {
        if ($depth > $this->maxDepth) { throw new \LengthException('Markdown tree nesting limit exceeded'); }
        foreach ($node->children as $child) { $this->validateDepth($child, $depth + 1); }
    }

    private function tick(): void
    {
        $this->budget->work();
    }
}
