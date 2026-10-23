<?php

namespace Wbengine\I18n;

/**
 * Looks messages up in catalogs, with a fallback chain, parameters and plural forms.
 *
 * A catalog is a PHP file returning an array, one file per locale ("cs.php", "en.php",
 * "pt_BR.php"). A value is a text, or an array of texts by plural category:
 *
 *   return [
 *       'greeting'   => 'Hello %name%',
 *       'files'      => ['one' => '%count% file', 'other' => '%count% files'],
 *   ];
 *
 * A message is looked up in the locale, then in its language ("pt_BR" then "pt"), then in
 * the default locale. If it is nowhere, the key itself is returned, so a missing
 * translation shows up on the page instead of a blank.
 *
 * Several directories can be given: later ones override earlier ones, so an application
 * can override the framework's own messages.
 */
class Translator
{
    private $_dirs = array();
    private $_default;
    private $_locale;
    private $_catalogs = array();
    private $_missing = array();

    /**
     * @param string[] $dirs directories holding the catalog files, lowest priority first
     * @param string $default locale used when a message is missing
     */
    public function __construct(array $dirs, $default = 'en')
    {
        $this->_dirs = array_values($dirs);
        $this->_default = self::normalize($default);
        $this->_locale = $this->_default;
    }

    /**
     * "cs-cz" and "CS_cz" both become "cs_CZ". Anything that is not a locale gives null.
     */
    public static function normalize($locale)
    {
        // Only plain whitespace is trimmed (trim() would also drop a NUL byte), and "D" makes
        // "$" match the very end, not before a trailing newline.
        if (!is_string($locale) || !preg_match('/^([A-Za-z]{2,3})(?:[_-]([A-Za-z]{2}))?$/D', trim($locale, " \t\n\r"), $m)) {
            return null;
        }
        return strtolower($m[1]) . (isset($m[2]) ? '_' . strtoupper($m[2]) : '');
    }

    public function setLocale($locale)
    {
        $normalized = self::normalize($locale);
        if (null !== $normalized) {
            $this->_locale = $normalized;
        }
        return $this;
    }

    public function getLocale()
    {
        return $this->_locale;
    }

    public function getDefaultLocale()
    {
        return $this->_default;
    }

    /**
     * The language of the current locale ("cs" for "cs_CZ").
     */
    public function getLanguage()
    {
        return explode('_', $this->_locale)[0];
    }

    /**
     * @param string $key
     * @param array $params replacements for %name% placeholders
     * @param int|float|null $count selects the plural form and fills %count%
     * @param string|null $locale translate into another locale than the current one
     * @return string
     */
    public function translate($key, array $params = array(), $count = null, $locale = null)
    {
        $key = (string) $key;
        $locale = (null === $locale) ? $this->_locale : (self::normalize($locale) ?? $this->_locale);

        $message = $this->_find($key, $locale);
        if (null === $message) {
            $this->_missing[$locale . ':' . $key] = true;
            $message = $key;
            $locale = $this->_default;
        }

        if (is_array($message)) {
            $category = Plural::category($locale, $count ?? 0);
            $message = $message[$category] ?? $message['other'] ?? reset($message);
            $message = (string) $message;
        }

        if (null !== $count) {
            $params += array('count' => $count);
        }
        return $this->_replace((string) $message, $params);
    }

    public function has($key, $locale = null)
    {
        return null !== $this->_find((string) $key, null === $locale ? $this->_locale : (self::normalize($locale) ?? $this->_locale));
    }

    /**
     * Keys that were asked for and not found, as "locale:key". Useful in development.
     */
    public function getMissing()
    {
        return array_keys($this->_missing);
    }

    // ------------------------------------------------------------ internals

    private function _find($key, $locale)
    {
        foreach ($this->_chain($locale) as $candidate) {
            $catalog = $this->_catalog($candidate);
            if (array_key_exists($key, $catalog)) {
                return $catalog[$key];
            }
        }
        return null;
    }

    /**
     * "pt_BR" -> pt_BR, pt, default
     */
    private function _chain($locale)
    {
        $chain = array($locale);
        if (false !== strpos($locale, '_')) {
            $chain[] = explode('_', $locale)[0];
        }
        $chain[] = $this->_default;
        if (false !== strpos($this->_default, '_')) {
            $chain[] = explode('_', $this->_default)[0];
        }
        return array_values(array_unique($chain));
    }

    private function _catalog($locale)
    {
        if (!isset($this->_catalogs[$locale])) {
            $messages = array();
            foreach ($this->_dirs as $dir) {
                $file = rtrim($dir, '/') . '/' . $locale . '.php';
                if (is_file($file)) {
                    $loaded = include $file;
                    if (is_array($loaded)) {
                        $messages = array_replace($messages, $loaded);
                    }
                }
            }
            $this->_catalogs[$locale] = $messages;
        }
        return $this->_catalogs[$locale];
    }

    private function _replace($message, array $params)
    {
        if ('' === $message || !$params || false === strpos($message, '%')) {
            return $message;
        }
        $pairs = array();
        foreach ($params as $name => $value) {
            if (is_scalar($value) || null === $value || (is_object($value) && method_exists($value, '__toString'))) {
                $pairs['%' . $name . '%'] = (string) $value;
            }
        }
        return strtr($message, $pairs);
    }
}
