<?php
namespace Wbengine\Markdown;

/** Finite HTML vocabulary with a cumulative output limit, including reference expansion. */
final class HtmlRenderer
{
    private string $output = '';
    private int $remaining = 0;

    public function __construct(private readonly int $maxDepth = 128, private readonly int $maxOutputBytes = 8388608)
    {
        if ($maxDepth < 1 || $maxOutputBytes < 1) { throw new \InvalidArgumentException('Markdown renderer limits must be positive'); }
    }

    public function render(Node $node): string
    {
        $this->output = ''; $this->remaining = $this->maxOutputBytes;
        try {
            $this->emit($node, 0);
            return $this->output;
        } finally { $this->output = ''; }
    }

    private function emit(Node $node, int $depth): void
    {
        if ($depth > $this->maxDepth) { throw new \LengthException('Markdown rendering nesting limit exceeded'); }
        $a = $node->attributes;
        switch ($node->type) {
            case 'document': case 'item_content': $this->children($node, $depth); return;
            case 'text': case 'html': case 'html_block': $this->append($this->escaped($node->literal)); return;
            case 'softbreak': $this->append("\n"); return;
            case 'hardbreak': $this->append("<br />\n"); return;
            case 'code': $this->append('<code>'); $this->append($this->escaped($node->literal)); $this->append('</code>'); return;
            case 'code_block':
                $this->append('<pre><code' . (!empty($a['info']) ? ' class="language-' . $this->escaped($a['info']) . '"' : '') . '>');
                $this->append($this->escaped($node->literal)); $this->append("</code></pre>\n"); return;
            case 'paragraph':
                if (empty($a['tight'])) { $this->element('p', $node, $depth); }
                else { $this->children($node, $depth); }
                return;
            case 'heading': $this->element('h' . max(1, min(6, (int) ($a['level'] ?? 1))), $node, $depth); return;
            case 'thematic_break': $this->append("<hr />\n"); return;
            case 'blockquote': $this->element('blockquote', $node, $depth, block: true); return;
            case 'list':
                $this->element(!empty($a['ordered']) ? 'ol' : 'ul', $node, $depth, !empty($a['ordered']) && ($a['start'] ?? 1) !== 1 ? ' start="' . (int) $a['start'] . '"' : '', true); return;
            case 'item':
                $this->append('<li>' . (!empty($a['block']) ? "\n" : ''));
                foreach ($node->children as $i => $child) {
                    $this->emit($child, $depth + 1);
                    if ($child->type === 'paragraph' && !empty($child->attributes['tight']) && isset($node->children[$i + 1])) { $this->append("\n"); }
                }
                $this->append("</li>\n"); return;
            case 'task': $this->append('<input' . (!empty($a['checked']) ? ' checked=""' : '') . ' disabled="" type="checkbox"> '); return;
            case 'emphasis': $this->element('em', $node, $depth, newline: false); return;
            case 'strong': $this->element('strong', $node, $depth, newline: false); return;
            case 'strikethrough': $this->element('del', $node, $depth, newline: false); return;
            case 'link':
                $attrs = ' href="' . $this->escaped($this->url($a['destination'] ?? '')) . '"' . $this->title($a);
                $this->element('a', $node, $depth, $attrs, newline: false); return;
            case 'image':
                $this->append('<img src="' . $this->escaped($this->url($a['destination'] ?? '')) . '" alt="' . $this->escaped($this->plain($node, $depth)) . '"' . $this->title($a) . ' />'); return;
            case 'table': case 'table_head': case 'table_body': case 'table_row':
                $tag = ['table' => 'table', 'table_head' => 'thead', 'table_body' => 'tbody', 'table_row' => 'tr'][$node->type];
                $this->element($tag, $node, $depth, block: true); return;
            case 'table_cell':
                $attrs = in_array($a['align'] ?? '', ['left', 'center', 'right'], true) ? ' align="' . $a['align'] . '"' : '';
                $this->element(!empty($a['header']) ? 'th' : 'td', $node, $depth, $attrs); return;
            default: throw new \UnexpectedValueException('Unknown Markdown node: ' . $node->type);
        }
    }

    private function element(string $tag, Node $node, int $depth, string $attrs = '', bool $block = false, bool $newline = true): void
    {
        $this->append('<' . $tag . $attrs . '>' . ($block ? "\n" : ''));
        $this->children($node, $depth);
        $this->append('</' . $tag . '>' . ($newline ? "\n" : ''));
    }

    private function children(Node $node, int $depth): void
    {
        foreach ($node->children as $child) { $this->emit($child, $depth + 1); }
    }

    private function append(string $html): void
    {
        $this->checkSize(strlen($html));
        $this->remaining -= strlen($html);
        $this->output .= $html;
    }

    private function checkSize(int $bytes): void
    {
        if ($bytes > $this->remaining) { throw new \LengthException('Markdown output size limit exceeded'); }
    }

    public static function escape(string $text): string
    {
        return str_replace('"', '&quot;', htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', true));
    }

    private function escaped(string $text): string
    {
        $this->checkSize(strlen($text));
        $escaped = self::escape($text);
        $this->checkSize(strlen($escaped));
        return $escaped;
    }

    private function title(array $a): string
    {
        return isset($a['title']) ? ' title="' . $this->escaped($a['title']) . '"' : '';
    }

    private function plain(Node $node, int $depth): string
    {
        if ($depth > $this->maxDepth) { throw new \LengthException('Markdown image nesting limit exceeded'); }
        if (in_array($node->type, ['text', 'code', 'html'], true)) { $this->checkSize(strlen($node->literal)); return $node->literal; }
        if (in_array($node->type, ['softbreak', 'hardbreak'], true)) { return "\n"; }
        $text = '';
        foreach ($node->children as $child) {
            $part = $this->plain($child, $depth + 1);
            $this->checkSize(strlen($text) + strlen($part));
            $text .= $part;
        }
        return $text;
    }

    private function url(string $url): string
    {
        $this->checkSize(strlen($url));
        // Decode before inspection so entity-obfuscated schemes cannot escape the allowlist.
        $check = preg_replace('/[\x00-\x20\x7f]/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $check, $scheme) && !in_array(strtolower($scheme[1]), ['http', 'https', 'mailto'], true)) { return ''; }
        $encoded = preg_replace_callback('/[^a-zA-Z0-9\-._~:\/?#@!$&\x27()*+,;=%]/', static fn($m) => rawurlencode($m[0]), $url);
        $this->checkSize(strlen($encoded));
        return $encoded;
    }
}
