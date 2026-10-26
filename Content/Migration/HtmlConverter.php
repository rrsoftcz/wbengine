<?php
namespace Wbengine\Content\Migration;

use DOMDocument;
use DOMElement;
use DOMNode;
use Wbengine\Markdown;

/** Conservative semantic conversion. Styling, scripts and unrepresented semantics require repair. */
final class HtmlConverter implements Converter
{
    private const ELEMENTS = ['body' => [], 'p' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'del' => [], 's' => [], 'a' => ['href', 'title'], 'img' => ['src', 'alt', 'title'], 'code' => ['class'], 'pre' => [], 'ul' => [], 'ol' => ['start'], 'li' => [], 'blockquote' => [], 'hr' => [], 'br' => [], 'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['align'], 'td' => ['align']];
    private array $issues = [];
    public function __construct(private readonly int $maxBytes = 1048576, private readonly int $maxDepth = 64) {}
    public function format(): string { return 'html'; }
    public function version(): string { return 'html-dom-1'; }

    public function convert(string $source): Conversion
    {
        if (strlen($source) > $this->maxBytes) { throw new \LengthException('Migration input size limit exceeded'); }
        $this->issues = [];
        if (!mb_check_encoding($source, 'UTF-8') || preg_match('/<!DOCTYPE|<!ENTITY|<\?|<\/?(?:html|head|body|meta|link|base)(?:\s|>)/i', $source)) {
            return new Conversion('', hash('sha256', $source), ['Document declarations, document-level HTML or invalid UTF-8 require manual repair']);
        }
        if (substr_count($source, '<') > 50000) { throw new \LengthException('Migration HTML node limit exceeded'); }
        $body = $this->document($source);
        $this->validate($body, 0);
        $markdown = trim($this->children($body, 0)) . ($source !== '' ? "\n" : '');
        if (strlen($markdown) > $this->maxBytes) { $this->issues[] = 'Converted Markdown exceeds the parser input limit; split or repair the content'; }
        if ($this->issues === []) {
            $rendered = (new Markdown())->render($markdown);
            if ($this->canonical($body, 0) !== $this->canonical($this->document($rendered), 0)) { $this->issues[] = 'Rendered Markdown does not preserve the supported HTML semantics'; }
        }
        return new Conversion($markdown, hash('sha256', $source), array_values(array_unique($this->issues)));
    }

    private function validate(DOMNode $node, int $depth): void
    {
        if ($depth > $this->maxDepth) { throw new \LengthException('Migration nesting limit exceeded'); }
        if ($node instanceof DOMElement) {
            $tag = strtolower($node->tagName);
            if (!isset(self::ELEMENTS[$tag])) { $this->issues[] = 'Unsupported element: ' . $tag; }
            else {
                foreach ($node->attributes as $attribute) {
                    if (!in_array(strtolower($attribute->name), self::ELEMENTS[$tag], true)) { $this->issues[] = 'Unsupported attribute: ' . $tag . '[' . $attribute->name . ']'; }
                }
            }
        }
        foreach ($node->childNodes as $child) { $this->validate($child, $depth + 1); }
    }

    private function document(string $html): DOMElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            libxml_clear_errors();
            $doc = new DOMDocument('1.0', 'UTF-8');
            $ok = $doc->loadHTML('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            if (!$ok) { throw new \UnexpectedValueException('Cannot parse the HTML fragment'); }
            foreach (libxml_get_errors() as $error) {
                if ($error->level >= LIBXML_ERR_ERROR) { $this->issues[] = 'HTML parser repair required: ' . trim($error->message); }
            }
            return $doc->getElementsByTagName('body')->item(0);
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }

    private function children(DOMNode $node, int $depth): string
    {
        if ($depth > $this->maxDepth) { throw new \LengthException('Migration nesting limit exceeded'); }
        $out = '';
        foreach ($node->childNodes as $child) { $out .= $this->node($child, $depth + 1); }
        return $out;
    }

    private function node(DOMNode $node, int $depth): string
    {
        if ($node->nodeType === XML_TEXT_NODE) { return $this->escape(preg_replace('/[ \t\r\n\f]+/', ' ', $node->nodeValue)); }
        if (!$node instanceof DOMElement) { $this->issues[] = 'Comments or processing instructions require manual review'; return ''; }
        $tag = strtolower($node->tagName);
        $allowed = self::ELEMENTS;
        if (!isset($allowed[$tag])) { $this->issues[] = 'Unsupported element: ' . $tag; return ''; }
        foreach ($node->attributes as $attribute) {
            if (!in_array(strtolower($attribute->name), $allowed[$tag], true)) { $this->issues[] = 'Unsupported attribute: ' . $tag . '[' . $attribute->name . ']'; }
        }
        if ($tag === 'ul' || $tag === 'ol') { return "\n" . $this->list($node, $depth); }
        if ($tag === 'pre') { return $this->pre($node); }
        if ($tag === 'table') { return $this->table($node, $depth); }
        if ($tag === 'code') {
            if ($node->hasAttribute('class')) { $this->issues[] = 'Inline code class requires manual review'; }
            $text = str_replace(["\r", "\n"], ' ', $node->textContent); $fence = str_repeat('`', max(1, $this->longestTicks($text) + 1));
            $pad = trim($text, ' ') === '' ? '' : ' ';
            return $fence . $pad . $text . $pad . $fence;
        }
        if ($tag === 'img') {
            return '![' . $this->escape($node->getAttribute('alt')) . '](' . $this->target($node, 'src') . ')';
        }
        $inner = $this->children($node, $depth);
        if (preg_match('/^h([1-6])$/', $tag, $m)) { return str_repeat('#', (int) $m[1]) . ' ' . trim($inner) . "\n\n"; }
        return match ($tag) {
            'p' => trim($inner) . "\n\n",
            'strong', 'b' => '**' . $inner . '**',
            'em', 'i' => '*' . $inner . '*',
            'del', 's' => '~~' . $inner . '~~',
            'a' => '[' . $inner . '](' . $this->target($node, 'href') . ')',
            'br' => "  \n", 'hr' => "\n---\n\n",
            'ul', 'ol' => $this->list($node, $depth),
            'li' => $inner,
            'blockquote' => preg_replace('/^/m', '> ', trim($inner)) . "\n\n",
            default => $inner,
        };
    }

    private function escape(string $text): string
    {
        return preg_replace('/([!"#$%&\x27()*+,\-.\/:;<=>?@\[\\\\\]^_`{|}~])/', '\\\\$1', $text);
    }

    private function target(DOMElement $node, string $attribute): string
    {
        $url = $node->getAttribute($attribute);
        $check = preg_replace('/[\x00-\x20\x7f]/', '', $url);
        if ((preg_match('/^([a-z][a-z0-9+.-]*):/i', $check, $m) && !in_array(strtolower($m[1]), ['http', 'https', 'mailto'], true)) || strpbrk($url, "\r\n<>") !== false) { $this->issues[] = 'Unsafe or unsupported URL: ' . $attribute; }
        $target = '<' . str_replace(['\\', '<', '>'], ['\\\\', '\\<', '\\>'], $url) . '>';
        if ($node->hasAttribute('title')) { $target .= ' "' . str_replace(['\\', '"'], ['\\\\', '\\"'], $node->getAttribute('title')) . '"'; }
        return $target;
    }

    private function longestTicks(string $text): int
    {
        preg_match_all('/`+/', $text, $m); return $m[0] ? max(array_map('strlen', $m[0])) : 0;
    }

    private function pre(DOMElement $node): string
    {
        $text = $node->textContent; $language = '';
        $elements = 0; $outsideText = '';
        foreach ($node->childNodes as $child) { if ($child instanceof DOMElement) { $elements++; } elseif ($child->nodeType === XML_TEXT_NODE) { $outsideText .= $child->nodeValue; } }
        if ($elements > 1 || ($elements > 0 && $outsideText !== '')) { $this->issues[] = 'Mixed or multiple pre/code children require manual repair'; }
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if ($child->tagName !== 'code') { $this->issues[] = 'Unsupported element inside pre'; continue; }
                foreach ($child->attributes as $a) { if ($a->name !== 'class') { $this->issues[] = 'Unsupported pre/code attribute'; } }
                $class = $child->getAttribute('class');
                if ($class !== '' && !preg_match('/^language-([a-zA-Z0-9_+-]+)$/', $class, $m)) { $this->issues[] = 'Unsupported code language class'; }
                elseif ($class !== '') { $language = $m[1]; }
                foreach ($child->childNodes as $descendant) { if ($descendant->nodeType !== XML_TEXT_NODE) { $this->issues[] = 'Nested markup inside code'; } }
            }
        }
        $fence = str_repeat('`', max(3, $this->longestTicks($text) + 1));
        return $fence . $language . "\n" . $text . (str_ends_with($text, "\n") ? '' : "\n") . $fence . "\n\n";
    }

    private function list(DOMElement $node, int $depth): string
    {
        $out = ''; $number = $node->hasAttribute('start') ? (int) $node->getAttribute('start') : 1;
        if ($number < 0 || $number > 999999999) { $this->issues[] = 'Unsupported ordered list start'; }
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement) { if (trim($child->textContent) !== '') { $this->issues[] = 'Text outside list item'; } continue; }
            if ($child->tagName !== 'li') { $this->issues[] = 'Unsupported list child'; continue; }
            $body = trim($this->node($child, $depth + 1));
            $marker = $node->tagName === 'ol' ? $number++ . '. ' : '- ';
            $out .= $marker . str_replace("\n", "\n" . str_repeat(' ', strlen($marker)), $body) . "\n";
        }
        return $out . "\n";
    }

    private function table(DOMElement $table, int $depth): string
    {
        $rows = []; $alignments = [];
        foreach ($table->getElementsByTagName('tr') as $r => $row) {
            $cells = [];
            foreach ($row->childNodes as $cell) {
                if (!$cell instanceof DOMElement) { continue; }
                if (!in_array($cell->tagName, ['th', 'td'], true) || ($r === 0 && $cell->tagName !== 'th')) { $this->issues[] = 'A Markdown table requires a header row'; }
                foreach ($cell->attributes as $a) { if ($a->name !== 'align') { $this->issues[] = 'Unsupported table cell attribute: ' . $a->name; } }
                $align = $cell->getAttribute('align');
                if (!in_array($align, ['', 'left', 'right', 'center'], true)) { $this->issues[] = 'Unsupported table alignment'; }
                if ($r === 0) { $alignments[] = $align; }
                elseif (($alignments[count($cells)] ?? '') !== $align) { $this->issues[] = 'Mixed table alignment requires repair'; }
                $cells[] = trim($this->children($cell, $depth + 1));
            }
            if (count($cells) !== count($alignments)) { $this->issues[] = 'Inconsistent table column count'; }
            $rows[] = '| ' . implode(' | ', $cells) . ' |';
        }
        if (!$rows) { $this->issues[] = 'Empty table'; return ''; }
        $delimiters = array_map(static fn($a) => match ($a) { 'left' => ':---', 'right' => '---:', 'center' => ':---:', default => '---' }, $alignments);
        array_splice($rows, 1, 0, ['| ' . implode(' | ', $delimiters) . ' |']);
        return implode("\n", $rows) . "\n\n";
    }

    private function canonical(DOMNode $node, int $depth): array|string|null
    {
        if ($depth > $this->maxDepth) { throw new \LengthException('Migration nesting limit exceeded'); }
        if ($node->nodeType === XML_TEXT_NODE) { return preg_replace('/[ \t\r\n\f]+/', ' ', $node->nodeValue); }
        if (!$node instanceof DOMElement) { return null; }
        $tag = match ($node->tagName) { 'b' => 'strong', 'i' => 'em', 's' => 'del', default => $node->tagName };
        if ($tag === 'pre') {
            $code = $node->getElementsByTagName('code')->item(0);
            return ['pre', preg_replace('/\n$/', '', $node->textContent), $code instanceof DOMElement ? $code->getAttribute('class') : ''];
        }
        $attributes = [];
        foreach ($node->attributes as $a) { $attributes[$a->name] = in_array($a->name, ['href', 'src'], true) ? rawurldecode($a->value) : $a->value; }
        ksort($attributes); $children = [];
        foreach ($node->childNodes as $child) {
            $value = $this->canonical($child, $depth + 1);
            $previous = $children ? $children[count($children) - 1] : null;
            if (is_string($value) && is_array($previous) && $previous[0] === 'br') { $value = ltrim($value); }
            if (is_string($value) && in_array($tag, ['body', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'blockquote'], true)) { $value = trim($value); }
            if ($value !== '' && $value !== null) {
                if (in_array($tag, ['table', 'li'], true) && is_array($value) && in_array($value[0], $tag === 'table' ? ['thead', 'tbody'] : ['p'], true)) { array_push($children, ...$value[2]); }
                else { $children[] = $value; }
            }
        }
        if ($children && in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'th', 'td'], true)) {
            if (is_string($children[0])) { $children[0] = ltrim($children[0]); }
            $last = count($children) - 1; if (is_string($children[$last])) { $children[$last] = rtrim($children[$last]); }
        }
        return [$tag, $attributes, $children];
    }
}
