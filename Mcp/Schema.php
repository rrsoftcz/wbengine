<?php

namespace Wbengine\Mcp;

/**
 * Validator for the JSON Schema subset the tools use: type, enum, required, properties,
 * additionalProperties, items, minLength, maxLength, minimum, maximum, maxItems, minItems, pattern.
 * Returns the first problem as text a model can act on, or null when the value is valid.
 */
final class Schema
{
    public static function validate(mixed $value, array $schema, string $path = 'arguments'): ?string
    {
        if (isset($schema['type']) && !self::isType($value, $schema['type'])) {
            return $path . ' must be ' . (is_array($schema['type']) ? implode(' or ', $schema['type']) : $schema['type']) . '.';
        }
        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            return $path . ' must be one of: ' . implode(', ', array_map(static fn($v) => json_encode($v), $schema['enum'])) . '.';
        }
        if (is_string($value)) {
            $length = mb_strlen($value);
            if (isset($schema['minLength']) && $length < $schema['minLength']) { return $path . ' must have at least ' . $schema['minLength'] . ' characters.'; }
            if (isset($schema['maxLength']) && $length > $schema['maxLength']) { return $path . ' must have at most ' . $schema['maxLength'] . ' characters.'; }
            if (isset($schema['pattern']) && !preg_match('/' . str_replace('/', '\/', $schema['pattern']) . '/u', $value)) { return $path . ' has an invalid format.'; }
        }
        if ((is_int($value) || is_float($value))) {
            if (isset($schema['minimum']) && $value < $schema['minimum']) { return $path . ' must be at least ' . $schema['minimum'] . '.'; }
            if (isset($schema['maximum']) && $value > $schema['maximum']) { return $path . ' must be at most ' . $schema['maximum'] . '.'; }
        }
        if (is_array($value) && array_is_list($value)) {
            if (isset($schema['minItems']) && count($value) < $schema['minItems']) { return $path . ' needs at least ' . $schema['minItems'] . ' items.'; }
            if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) { return $path . ' allows at most ' . $schema['maxItems'] . ' items.'; }
            if (isset($schema['items'])) {
                foreach ($value as $i => $item) {
                    if (null !== ($problem = self::validate($item, $schema['items'], $path . '[' . $i . ']'))) { return $problem; }
                }
            }
        }
        if (is_array($value) && (($schema['type'] ?? null) === 'object')) {
            foreach ($schema['required'] ?? [] as $name) {
                if (!array_key_exists($name, $value)) { return $path . ' is missing the required field "' . $name . '".'; }
            }
            $properties = $schema['properties'] ?? [];
            foreach ($value as $name => $item) {
                if (isset($properties[$name])) {
                    if (null !== ($problem = self::validate($item, $properties[$name], $path . '.' . $name))) { return $problem; }
                } elseif (false === ($schema['additionalProperties'] ?? true)) {
                    return $path . ' has the unknown field "' . $name . '".';
                }
            }
        }
        return null;
    }

    private static function isType(mixed $value, string|array $type): bool
    {
        foreach ((array) $type as $one) {
            $ok = match ($one) {
                'string' => is_string($value),
                'integer' => is_int($value),
                'number' => is_int($value) || is_float($value),
                'boolean' => is_bool($value),
                'array' => is_array($value) && array_is_list($value),
                'object' => is_array($value) && ([] === $value || !array_is_list($value)),
                'null' => null === $value,
                default => false,
            };
            if ($ok) { return true; }
        }
        return false;
    }
}
