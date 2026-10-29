<?php

namespace Wbengine\Api\V2;

/** Shared validation and wire contract for explicitly registered v2 resources. */
class Contract
{
    public static function success($data, ?array $pagination = null): array
    {
        $body = ['success' => true, 'data' => $data];
        if (null !== $pagination) {
            $body['pagination'] = $pagination;
        }
        return $body;
    }

    public static function error(Problem $problem): array
    {
        return ['success' => false, 'error' => ['code' => $problem->errorCode, 'message' => $problem->getMessage()]];
    }

    /** JSON lists, including an empty list, are distinct from objects. */
    public static function objectBody(string $json, array $allowedFields): array
    {
        if (strlen($json) > 1048576) {
            throw new Problem('body_too_large', 'Request body is too large.', 413);
        }
        try {
            $object = json_decode($json, false, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new Problem('invalid_body', 'The body must be a JSON object.');
        }
        if (!$object instanceof \stdClass) {
            throw new Problem('invalid_body', 'The body must be a JSON object.');
        }
        $data = get_object_vars($object);
        if (array_diff(array_keys($data), $allowedFields)) {
            throw new Problem('invalid_field', 'The body contains an unsupported field.');
        }
        return $data;
    }

    /** Page numbers start at 1; a page contains at most 100 items. */
    public static function pagination(array $query): array
    {
        $page = self::boundedInteger($query['page'] ?? 1, 1, 1000000);
        $perPage = self::boundedInteger($query['per_page'] ?? 25, 1, 100);
        return ['page' => $page, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage];
    }

    /** Optional "search" text of a list: null when absent or blank, otherwise a trimmed string of at most 100 characters. */
    public static function search(array $query): ?string
    {
        if (!array_key_exists('search', $query)) { return null; }
        if (!is_string($query['search']) || mb_strlen(trim($query['search'])) > 100 || preg_match('/[\x00-\x1f\x7f]/', $query['search'])) {
            throw new Problem('invalid_search', 'The search text must be a string of at most 100 characters.');
        }
        $text = trim($query['search']);
        return '' === $text ? null : $text;
    }

    /** A LIKE pattern matching the text anywhere, with the wildcard characters of the text escaped (use with ESCAPE '\\'). */
    public static function likePattern(string $text): string
    {
        return '%' . addcslashes($text, '\\%_') . '%';
    }

    public static function page(array $items, array $query, int $total): array
    {
        $pagination = self::pagination($query);
        return self::success($items, [
            'page' => $pagination['page'],
            'per_page' => $pagination['per_page'],
            'total' => max(0, $total),
            'total_pages' => (int) ceil(max(0, $total) / $pagination['per_page']),
        ]);
    }

    private static function boundedInteger($value, int $min, int $max): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]{0,6}$/D', (string) $value) || (int) $value < $min || (int) $value > $max) {
            throw new Problem('invalid_pagination', 'Invalid pagination parameters.');
        }
        return (int) $value;
    }
}
