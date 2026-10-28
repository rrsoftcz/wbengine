<?php
namespace Wbengine\Api\V2;

/** Finite mappings to existing CMS tables and columns. No remote table/class selectors. */
final class Resources
{
    private const DEFINITIONS = [
        'articles' => ['table' => 'cms_articles', 'id' => 'id', 'required' => ['title', 'introtext', 'bodytext'], 'fields' => ['site_id' => 'id0', 'title' => 255, 'introtext' => 65535, 'bodytext' => 65535, 'author' => 255, 'source' => 255, 'content_format' => 'format']],
        'pages' => ['table' => 'cms_sites', 'id' => 'site_id', 'required' => ['link', 'title'], 'fields' => ['parent_id' => 'id0', 'strict' => 'bool', 'visible' => 'bool', 'link' => 100, 'title' => 120, 'html_title' => 120, 'html_description' => 170, 'html_keywords' => 255, 'acl' => 'bool']],
        'menu' => ['table' => 'cms_menu', 'id' => 'menu_id', 'required' => ['site_id', 'name'], 'fields' => ['site_id' => 'id', 'type' => 'tiny', 'name' => 100, 'description' => 50, 'visible' => 'bool', 'order' => 'tiny', 'parent' => 'id0']],
        'sections' => ['table' => 'cms_sections', 'id' => 'section_id', 'required' => ['key', 'title'], 'fields' => ['title' => 100, 'description' => 150, 'active' => 'bool', 'key' => 45, 'return_error_code' => 'status']],
        'boxes' => ['table' => 'cms_boxes', 'id' => 'id', 'required' => ['name', 'type', 'section_id'], 'fields' => ['name' => 100, 'type' => 45, 'settings' => 'object', 'section_id' => 'id', 'static' => 'bool', 'shared' => 'bool', 'device_min' => 'device', 'device_strict' => 'bool', 'order' => 'tiny', 'sites' => 'ids']],
    ];

    public static function names(): array { return array_keys(self::DEFINITIONS); }

    public static function definition(string $resource): array
    {
        if (!isset(self::DEFINITIONS[$resource])) { throw new Problem('invalid_resource', 'Unsupported resource.'); }
        return self::DEFINITIONS[$resource];
    }

    public static function id($id): int
    {
        if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string) $id) || (int) $id > 2147483647) {
            throw new Problem('invalid_id', 'Invalid resource identifier.');
        }
        return (int) $id;
    }

    public static function validate(string $resource, array $data, ?array $current = null, bool $creating = false): array
    {
        $definition = self::definition($resource);
        if (!$data || array_diff(array_keys($data), array_keys($definition['fields']))) { throw new Problem('invalid_field', 'Unsupported or empty resource fields.'); }
        if ($creating && array_diff($definition['required'], array_keys($data))) { throw new Problem('missing_field', 'A required field is missing.'); }
        foreach ($data as $field => $value) {
            $type = $definition['fields'][$field];
            if (null === $value && in_array($field, ['description', 'author', 'source'], true)) { continue; }
            if (is_int($type)) {
                if (!is_string($value) || strlen($value) > $type || !preg_match('//u', $value) || str_contains($value, "\0")) { throw new Problem('invalid_field', 'Invalid text field.'); }
            } elseif ('object' === $type) {
                if ($value instanceof \stdClass) { $data[$field] = get_object_vars($value); }
                elseif (!is_array($value) || ($value && array_is_list($value))) { throw new Problem('invalid_field', 'Settings must be an object.'); }
            } elseif ('ids' === $type) {
                if (!is_array($value) || !array_is_list($value) || count($value) > 100) { throw new Problem('invalid_field', 'Sites must be a list of up to 100 IDs.'); }
                $data[$field] = array_values(array_unique(array_map(self::id(...), $value)));
            } elseif ('format' === $type) {
                if ('markdown' !== $value) { throw new Problem('invalid_format', 'New content must use Markdown.'); }
            } else {
                $max = match ($type) { 'bool' => 1, 'tiny' => 127, 'device' => 3, 'status' => 599, default => 2147483647 };
                $min = 'id' === $type ? 1 : 0;
                if (!is_int($value) || $value < $min || $value > $max) { throw new Problem('invalid_field', 'Invalid numeric field.'); }
            }
        }
        if (isset($data['link']) && (!str_starts_with($data['link'], '/') || preg_match('~[\\\\?#\x00-\x20]|(^|/)(\.|\.\.)(/|$)|%2[ef]|%5c~i', $data['link']) || str_starts_with($data['link'], '//'))) {
            throw new Problem('invalid_link', 'Page links must be local absolute paths.');
        }
        if ('articles' === $resource && isset($data['source']) && preg_match('/[\x00-\x1f\x7f]/', $data['source'])) {
            throw new Problem('invalid_source', 'Source must be plain text without control characters.');
        }
        if (isset($data['return_error_code']) && 0 !== $data['return_error_code'] && $data['return_error_code'] < 400) { throw new Problem('invalid_status', 'Sections accept zero or an HTTP error status.'); }
        if (isset($data['key']) && !preg_match('/^[a-z][a-z0-9_]{0,44}$/D', $data['key'])) { throw new Problem('invalid_key', 'Invalid section key.'); }
        if ('articles' === $resource) {
            if ($creating) { $data['content_format'] ??= 'markdown'; }
            $oldFormat = $current['content_format'] ?? 'legacy_html';
            $textChanged = false;
            foreach (['introtext', 'bodytext'] as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== ($current[$field] ?? null)) { $textChanged = true; }
            }
            if (!$creating && 'markdown' !== $oldFormat && $textChanged && (($data['content_format'] ?? null) !== 'markdown' || !array_key_exists('introtext', $data) || !array_key_exists('bodytext', $data))) {
                throw new Problem('format_replacement_required', 'Changing legacy text requires explicit Markdown and replacement of both text fields.');
            }
            if (!$creating && isset($data['content_format']) && $oldFormat !== $data['content_format'] && (!array_key_exists('introtext', $data) || !array_key_exists('bodytext', $data))) {
                throw new Problem('format_replacement_required', 'Changing format requires replacing both text fields.');
            }
        }
        return $data;
    }

    public static function etag(array $state): string
    {
        return '"' . hash('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"';
    }

    public static function match(?string $expected, array $state): void
    {
        if (null === $expected || '' === $expected) { throw new Problem('precondition_required', 'If-Match is required.', 428); }
        if (!hash_equals(self::etag($state), $expected)) { throw new Problem('precondition_failed', 'The resource changed. Reload before saving.', 412); }
    }
}
