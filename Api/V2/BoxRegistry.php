<?php
namespace Wbengine\Api\V2;

/** Only website startup code registers executable box pairs. HTTP input selects a type ID. */
final class BoxRegistry
{
    private static ?self $current = null;
    private array $types = [];

    public static function current(): self { return self::$current ??= new self(); }

    /** Legacy boxes are unregistered, so the framework article renderer is recognised by its names. */
    private const LEGACY_ARTICLE_METHOD = 'getArticleBox';
    private const LEGACY_ARTICLE_MODULE = 'article';

    /** Settings are finite enum-valued configuration, never PHP names or source. */
    public function register(string $type, string $module, string $method, array $settings = [], array $sections = ['central'], array $metadata = []): void
    {
        foreach ([$type, $module, $method] as $identifier) {
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,44}$/D', $identifier)) {
                throw new \InvalidArgumentException('Invalid registered box identifier.');
            }
        }
        foreach ($settings as $name => $values) {
            if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_]{0,44}$/D', $name) || !is_array($values) || !array_is_list($values) || !$values) {
                throw new \InvalidArgumentException('Box settings require named non-empty enums.');
            }
            foreach ($values as $value) {
                if (!is_string($value) || strlen($value) > 128) { throw new \InvalidArgumentException('Invalid box setting enum value.'); }
            }
        }
        if (!$sections || !array_is_list($sections)) { throw new \InvalidArgumentException('Allowed section keys are required.'); }
        foreach ($sections as $section) { if (!is_string($section) || !preg_match('/^[a-z][a-z0-9_]{0,44}$/D', $section)) { throw new \InvalidArgumentException('Invalid section key.'); } }
        if (array_diff_key($metadata, ['renders' => true]) || (isset($metadata['renders']) && 'article' !== $metadata['renders'])) {
            throw new \InvalidArgumentException('Box metadata allows only renders=article.');
        }
        if (isset($this->types[$type])) { throw new \LogicException('Box type already registered.'); }
        $this->types[$type] = ['module' => $module, 'method' => $method, 'settings' => $settings, 'sections' => $sections, 'renders' => $metadata['renders'] ?? null];
    }

    public function catalog(): array
    {
        $result = [];
        foreach ($this->types as $id => $definition) { $result[] = ['type' => $id, 'settings' => $definition['settings'], 'sections' => $definition['sections'], 'renders' => $definition['renders']]; }
        return $result;
    }

    public function resolve(string $type, array $settings): array
    {
        if (!isset($this->types[$type])) { throw new Problem('invalid_box_type', 'This box type is not registered.'); }
        $definition = $this->types[$type];
        foreach ($settings as $name => $value) {
            if (!isset($definition['settings'][$name]) || !in_array($value, $definition['settings'][$name], true)) {
                throw new Problem('invalid_box_settings', 'Unsupported box setting.');
            }
        }
        return ['module' => $definition['module'], 'method' => $definition['method']];
    }

    public function assertSection(string $type, string $section): void
    {
        if (!isset($this->types[$type]) || !in_array($section, $this->types[$type]['sections'], true)) {
            throw new Problem('invalid_box_section', 'This box type is not registered for this section.');
        }
    }

    /**
     * Whether a box renders articles: its registered type says renders=article, or, for a legacy
     * box that is not registered, its module/method are the framework article renderer.
     */
    public function rendersArticles(string $module, string $method): bool
    {
        $type = $this->typeFor($module, $method);
        if (null !== $type) { return 'article' === $this->types[$type]['renders']; }
        return 0 === strcasecmp($method, self::LEGACY_ARTICLE_METHOD) || 0 === strcasecmp($module, self::LEGACY_ARTICLE_MODULE);
    }

    public function typeFor(string $module, string $method): ?string
    {
        foreach ($this->types as $type => $definition) {
            if ($definition['module'] === $module && $definition['method'] === $method) { return $type; }
        }
        return null;
    }
}
