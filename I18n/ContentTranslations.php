<?php

namespace Wbengine\I18n;

use Wbengine\Cache;
use Wbengine\Db;
use Wbengine\I18n;

/**
 * Translations of content that lives in the database: the title and meta text of a site,
 * the name of a menu entry, the title and text of an article.
 *
 * The content in its own table is the text in the DEFAULT language, so nothing has to be
 * migrated and a site that does not use i18n is untouched. A translation is a row in a
 * translation table for one item and one locale. Each field falls back on its own: a
 * translation that is missing, NULL or empty shows the default-language text for that
 * field, so a half translated page has no holes.
 *
 * Reading is an overlay on rows the models already loaded: one extra query for a request in
 * another language (none for the small site and menu tables when the cache is on). If the
 * translation tables do not exist the content is shown in the default language and the
 * problem is logged, the site keeps working.
 */
class ContentTranslations
{
    /**
     * entity => the translation table and its key, the table the items are in, and the
     * translatable fields with their maximum length (in characters).
     */
    const ENTITIES = array(
        'site' => array(
            'table' => S_TABLE_SITE_TRANSLATIONS, 'key' => 'site_id', 'source' => S_TABLE_SITES, 'id' => 'site_id',
            'fields' => array('title' => 120, 'html_title' => 120, 'html_description' => 170, 'html_keywords' => 255),
            'cache' => true,
        ),
        'menu' => array(
            'table' => S_TABLE_MENU_TRANSLATIONS, 'key' => 'menu_id', 'source' => S_TABLE_MENU, 'id' => 'menu_id',
            'fields' => array('name' => 100, 'description' => 50),
            'cache' => true,
        ),
        'article' => array(
            'table' => S_TABLE_ARTICLE_TRANSLATIONS, 'key' => 'article_id', 'source' => S_TABLE_ARTICLES, 'id' => 'id',
            'fields' => array('title' => 255, 'introtext' => 65535, 'bodytext' => 65535),
            'cache' => false,
        ),
    );

    private static $_degradedLogged = false;

    // ------------------------------------------------------------- reading

    /**
     * The locales to look at for the current request, best first, or an empty array when
     * the content is shown as it is (i18n off, or the default language).
     */
    public static function chain()
    {
        if (!I18n::isEnabled()) {
            return array();
        }
        $locale = I18n::locale();
        $default = I18n::translator()->getDefaultLocale();
        if ($locale === $default) {
            return array();
        }
        $chain = array($locale);
        if (false !== strpos($locale, '_')) {
            $language = explode('_', $locale)[0];
            if ($language !== $default) {
                $chain[] = $language;
            }
        }
        return $chain;
    }

    /**
     * Translate one row (an array or an object with the id of the entity in it).
     *
     * @param string $entity site, menu or article
     * @param array|object|null $row
     * @return array|object|null the row, translated where a translation exists
     */
    public static function overlayRow($entity, $row)
    {
        if (null === $row || false === $row) {
            return $row;
        }
        $result = self::overlayRows($entity, array($row));
        return $result[0];
    }

    /**
     * Translate a list of rows. Rows keep their order and their type (array or object).
     */
    public static function overlayRows($entity, array $rows)
    {
        $chain = self::chain();
        if (!$rows || !$chain || !isset(self::ENTITIES[$entity])) {
            return $rows;
        }
        $def = self::ENTITIES[$entity];

        $ids = array();
        foreach ($rows as $row) {
            $id = is_object($row) ? ($row->{$def['id']} ?? null) : ($row[$def['id']] ?? null);
            if (null !== $id && is_numeric($id)) {
                $ids[(int) $id] = true;
            }
        }
        if (!$ids) {
            return $rows;
        }

        try {
            $translations = self::_load($entity, array_keys($ids), $chain);
        } catch (\Throwable $e) {
            if (!self::$_degradedLogged) {
                self::$_degradedLogged = true;
                error_log(__CLASS__ . ': translations are not available, content is shown in the default language (' . $e->getMessage() . '). Are the translation tables present?');
            }
            return $rows;
        }

        foreach ($rows as $i => $row) {
            $id = (int) (is_object($row) ? ($row->{$def['id']} ?? 0) : ($row[$def['id']] ?? 0));
            if (!isset($translations[$id])) {
                continue;
            }
            foreach ($def['fields'] as $field => $unused) {
                foreach ($chain as $locale) {
                    $value = $translations[$id][$locale][$field] ?? null;
                    if (null !== $value && '' !== $value) {
                        if (is_object($row)) {
                            $row->$field = $value;
                        } else {
                            $row[$field] = $value;
                        }
                        break;
                    }
                }
            }
            $rows[$i] = $row;
        }
        return $rows;
    }

    /**
     * id => locale => field => text, for the given locales.
     */
    private static function _load($entity, array $ids, array $chain)
    {
        $def = self::ENTITIES[$entity];
        $fields = implode(', ', array_map(array(Db::class, 'identifier'), array_keys($def['fields'])));
        $key = Db::identifier($def['key']);
        $table = Db::identifier($def['table']);
        $marks = implode(',', array_fill(0, count($chain), '?'));

        // Small tables are loaded whole, once per locale, and cached: the number of entries
        // is bounded by the number of locales, never by what a visitor asks for.
        if ($def['cache']) {
            $all = Cache::remember('i18n:' . $entity . ':' . implode(',', $chain), function () use ($fields, $key, $table, $marks, $chain) {
                return Db::fetchAllAssoc(sprintf('SELECT %s, `locale`, %s FROM %s WHERE `locale` IN (%s)', $key, $fields, $table, $marks), $chain);
            }, null, true);
            return self::_group($all, $def['key']);
        }

        $idMarks = implode(',', array_fill(0, count($ids), '?'));
        $rows = Db::fetchAllAssoc(
            sprintf('SELECT %s, `locale`, %s FROM %s WHERE %s IN (%s) AND `locale` IN (%s)', $key, $fields, $table, $key, $idMarks, $marks),
            array_merge($ids, $chain)
        );
        return self::_group($rows, $def['key']);
    }

    private static function _group($rows, $key)
    {
        $grouped = array();
        foreach ((array) $rows as $row) {
            $grouped[(int) $row[$key]][$row['locale']] = $row;
        }
        return $grouped;
    }

    // ------------------------------------------------------------- writing

    /**
     * All translations of one item: locale => field => text.
     *
     * @throws TranslationException
     */
    public static function all($entity, $id)
    {
        $def = self::_entity($entity);
        self::_requireItem($def, $id);

        $fields = implode(', ', array_map(array(Db::class, 'identifier'), array_keys($def['fields'])));
        $rows = Db::fetchAllAssoc(
            sprintf('SELECT `locale`, %s FROM %s WHERE %s = ? ORDER BY `locale`', $fields, Db::identifier($def['table']), Db::identifier($def['key'])),
            array((int) $id)
        );

        $out = array();
        foreach ($rows as $row) {
            $locale = $row['locale'];
            unset($row['locale']);
            $out[$locale] = $row;
        }
        return $out;
    }

    /**
     * Store the given fields of a translation, creating it when it does not exist. Fields
     * that are not given are left as they are; null or an empty text means "use the default".
     *
     * @return array the translation as stored
     * @throws TranslationException
     */
    public static function set($entity, $id, $locale, array $fields)
    {
        $def = self::_entity($entity);
        $locale = self::_locale($locale);
        self::_requireItem($def, $id);

        if (!$fields) {
            throw new TranslationException('There is nothing to store.');
        }
        $values = array();
        foreach ($fields as $name => $value) {
            if (!is_string($name) || !isset($def['fields'][$name])) {
                throw new TranslationException(sprintf('Unknown field "%s" for %s, allowed: %s.', is_string($name) ? $name : '?', $entity, implode(', ', array_keys($def['fields']))));
            }
            if (null !== $value && !is_string($value)) {
                throw new TranslationException(sprintf('The field "%s" must be text or null.', $name));
            }
            if (null !== $value && mb_strlen($value, 'UTF-8') > $def['fields'][$name]) {
                throw new TranslationException(sprintf('The field "%s" is longer than %d characters.', $name, $def['fields'][$name]));
            }
            $values[$name] = (null === $value || '' === $value) ? null : $value;
        }

        $table = Db::identifier($def['table']);
        $key = Db::identifier($def['key']);
        // Create the row if it is not there, then update. Both steps are safe to repeat and to
        // run at the same time (the key is unique).
        Db::query(sprintf('INSERT IGNORE INTO %s (%s, `locale`) VALUES (?, ?)', $table, $key), array((int) $id, $locale));
        $set = implode(', ', array_map(static function ($f) {
            return Db::identifier($f) . ' = ?';
        }, array_keys($values)));
        Db::query(sprintf('UPDATE %s SET %s WHERE %s = ? AND `locale` = ?', $table, $set, $key), array_merge(array_values($values), array((int) $id, $locale)));

        Cache::flush();
        return self::all($entity, $id)[$locale] ?? array();
    }

    /**
     * Remove a translation: the item is shown in the default language again.
     *
     * @return bool whether there was one
     * @throws TranslationException
     */
    public static function delete($entity, $id, $locale)
    {
        $def = self::_entity($entity);
        $locale = self::_locale($locale);
        self::_requireItem($def, $id);

        Db::query(
            sprintf('DELETE FROM %s WHERE %s = ? AND `locale` = ?', Db::identifier($def['table']), Db::identifier($def['key'])),
            array((int) $id, $locale)
        );
        $deleted = Db::getAffected() > 0;
        Cache::flush();
        return $deleted;
    }

    public static function entities()
    {
        return array_keys(self::ENTITIES);
    }

    // ------------------------------------------------------------- internals

    private static function _entity($entity)
    {
        if (!is_string($entity) || !isset(self::ENTITIES[$entity])) {
            throw new TranslationException(sprintf('Unknown content type "%s", allowed: %s.', is_string($entity) ? $entity : '?', implode(', ', array_keys(self::ENTITIES))), TranslationException::NOT_FOUND);
        }
        return self::ENTITIES[$entity];
    }

    /**
     * A supported locale in its configured spelling. The default language is refused: it is
     * the content itself.
     */
    private static function _locale($locale)
    {
        $match = LocaleResolver::match($locale, I18n::locales());
        if (null === $match) {
            throw new TranslationException(sprintf('"%s" is not a supported locale, supported: %s.', is_scalar($locale) ? $locale : '?', implode(', ', I18n::locales())));
        }
        $default = I18n::translator()->getDefaultLocale();
        if (Translator::normalize($match) === $default) {
            throw new TranslationException('The default language is the content itself, it has no translation.');
        }
        return Translator::normalize($match);
    }

    private static function _requireItem(array $def, $id)
    {
        if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id < 1) {
            throw new TranslationException('The id must be a positive number.');
        }
        $row = Db::fetchAssoc(
            sprintf('SELECT %s FROM %s WHERE %s = ? LIMIT 1', Db::identifier($def['id']), Db::identifier($def['source']), Db::identifier($def['id'])),
            array((int) $id)
        );
        if (!is_array($row)) {
            throw new TranslationException('The item does not exist.', TranslationException::NOT_FOUND);
        }
    }
}
