<?php

namespace Wbengine\Template;

use Wbengine\Template\Exception\TemplateException;

/**
 * Renders templates by compiling them to PHP files once and including those files.
 * With opcache a render is just running already compiled PHP.
 *
 * Template names are resolved inside the template directory and the extra roots
 * only, never outside of them.
 */
class Engine
{
    private $_templateDir = null;
    private $_compileDir = null;
    private $_roots = array();
    private $_vars = array();
    private $_autoEscape = true;
    private $_compileCheck = true;
    private $_depth = 0;
    private $_fragmentCache = null;
    private $_cacheUserResolver = null;

    /** Compiled templates already loaded in this process, so a template rendered again is not included again. */
    private $_loaded = array();

    const MAX_INCLUDE_DEPTH = 16;

    public function setTemplateDir($dir)
    {
        $this->_templateDir = $this->_realDir($dir, 'template');
        return $this;
    }

    public function setCompileDir($dir)
    {
        $dir = rtrim((string) $dir, '/');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new TemplateException(sprintf('The compile directory "%s" cannot be created.', $dir));
        }
        if (!is_writable($dir)) {
            throw new TemplateException(sprintf('The compile directory "%s" is not writable.', $dir));
        }
        $this->_compileDir = realpath($dir);
        return $this;
    }

    /**
     * Allow templates outside the template directory (for example the ones bundled with
     * the framework). They can be referenced by absolute path.
     */
    public function addRoot($dir)
    {
        $real = realpath((string) $dir);
        if (false !== $real) {
            $this->_roots[$real] = true;
        }
        return $this;
    }

    public function setAutoEscape($value)
    {
        $this->_autoEscape = (bool) $value;
        return $this;
    }

    public function getAutoEscape(): bool
    {
        return $this->_autoEscape;
    }

    public function setCompileCheck($value)
    {
        $this->_compileCheck = (bool) $value;
        return $this;
    }

    /** Called lazily only for a user-scoped fragment, never for public rendering. */
    public function setCacheUserResolver(?callable $resolver)
    {
        $this->_cacheUserResolver = $resolver;
        return $this;
    }

    public function resolveCacheUserId(): ?int
    {
        if (null === $this->_cacheUserResolver) {
            return null;
        }
        try {
            $id = ($this->_cacheUserResolver)();
        } catch (\Throwable $e) {
            error_log(__METHOD__ . ': user context unavailable (' . get_class($e) . ')');
            return null;
        }
        if (!is_int($id) && !is_string($id)) {
            return null;
        }
        $valid = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return false === $valid ? null : $valid;
    }

    public function cache($key, $ttl, callable $render, string $scope = 'public'): string
    {
        if (null === $this->_fragmentCache) {
            $this->_fragmentCache = new FragmentCache($this);
        }
        return $this->_fragmentCache->render($key, $ttl, $render, $scope);
    }

    public function assign($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $key => $item) {
                $this->_vars[(string) $key] = $item;
            }
        } elseif (is_string($name) && '' !== $name) {
            $this->_vars[$name] = $value;
        }
        return $this;
    }

    public function fetch($name, array $extra = array())
    {
        return $this->_render($name, array_merge($this->_vars, $extra));
    }

    public function display($name)
    {
        echo $this->fetch($name);
    }

    /**
     * Used by {include}: renders another template with the variables of the caller.
     */
    public function partial($name, array $vars)
    {
        return $this->_render(Runtime::s($name), $vars);
    }

    // ---------------------------------------------------------------- internals

    private function _realDir($dir, $what)
    {
        $real = realpath((string) $dir);
        if (false === $real || !is_dir($real)) {
            throw new TemplateException(sprintf('The %s directory "%s" does not exist.', $what, $dir));
        }
        return $real;
    }

    private function _render($name, array $vars)
    {
        if ($this->_depth >= self::MAX_INCLUDE_DEPTH) {
            throw new TemplateException('Templates are included too deep (a template includes itself?).');
        }

        $compiled = $this->_compiled($this->_resolve($name), $name);
        $fn = $this->_loaded[$compiled] ?? ($this->_loaded[$compiled] = include $compiled);

        ++$this->_depth;
        ob_start();
        try {
            $fn($vars, $this);
            return ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        } finally {
            --$this->_depth;
        }
    }

    /**
     * The real path of a template, which must be inside an allowed root.
     */
    private function _resolve($name)
    {
        if (!is_string($name) || '' === $name || false !== strpos($name, "\0")) {
            throw new TemplateException('The template name is empty or invalid.');
        }
        if (null === $this->_templateDir) {
            throw new TemplateException('The template directory is not set.');
        }

        $path = ('/' === $name[0]) ? $name : $this->_templateDir . '/' . $name;
        $real = realpath($path);
        if (false === $real || !is_file($real)) {
            throw new TemplateException(sprintf('The template "%s" was not found.', $name));
        }

        $roots = array_merge(array($this->_templateDir => true), $this->_roots);
        foreach ($roots as $root => $unused) {
            if (0 === strpos($real, $root . DIRECTORY_SEPARATOR)) {
                return $real;
            }
        }
        throw new TemplateException(sprintf('The template "%s" is outside the template directories.', $name));
    }

    /**
     * Path of the compiled PHP file for a template, compiling it when needed.
     */
    private function _compiled($source, $name)
    {
        if (null === $this->_compileDir) {
            throw new TemplateException('The compile directory is not set.');
        }

        $file = $this->_compileDir . '/' . hash('sha256', $source . '|' . Compiler::VERSION . '|' . (int) $this->_autoEscape) . '.php';

        if (is_file($file) && (!$this->_compileCheck || $this->_isFresh($source, $file))) {
            return $file;
        }

        unset($this->_loaded[$file]); // about to be replaced
        $sourceText = (string) file_get_contents($source);
        $code = (new Compiler($this->_autoEscape))->compile($sourceText, $name, $this->_signature($source, $sourceText));

        $tmp = tempnam($this->_compileDir, 'tpl');
        if (false === $tmp || false === file_put_contents($tmp, $code) || !@rename($tmp, $file)) {
            @unlink((string) $tmp);
            throw new TemplateException(sprintf('The compiled template cannot be written to "%s".', $file));
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
        return $file;
    }

    /**
     * Development freshness check, including same-size edits within one second.
     * With compileCheck off this checksum is not computed on a cache hit.
     */
    private function _signature($source, ?string $contents = null)
    {
        clearstatcache(true, $source);
        $hash = null === $contents ? hash_file('sha256', $source) : hash('sha256', $contents);
        return filemtime($source) . ':' . filesize($source) . ':' . $hash;
    }

    private function _isFresh($source, $compiled)
    {
        $handle = @fopen($compiled, 'r');
        if (false === $handle) {
            return false;
        }
        fgets($handle);
        fgets($handle);
        $line = (string) fgets($handle);
        fclose($handle);

        return 'source-signature: ' . $this->_signature($source) === trim(substr($line, 3));
    }
}
