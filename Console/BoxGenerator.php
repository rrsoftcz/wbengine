<?php
namespace Wbengine\Console;

/** Explicit scaffolding only. SQL is emitted for review and is never executed. */
final class BoxGenerator
{
    public function generate(string $root, string $namespace, string $section, string $module, string $method, bool $write = false): array
    {
        foreach (array_merge(explode('\\', $namespace), [$section, $module, $method]) as $identifier) {
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $identifier)
                || in_array(strtolower($identifier), explode(' ', 'fn and or xor as instanceof insteadof goto enddeclare endfor endforeach endif endswitch endwhile class interface trait enum function namespace use extends implements public protected private static abstract final readonly match new return echo print exit die eval isset empty unset clone yield parent self static true false null int string bool float object array callable iterable void never mixed resource list global const switch case default break continue throw try catch finally if else elseif for foreach while do declare include require include_once require_once __halt_compiler __construct __destruct'), true)) {
                throw new \InvalidArgumentException('Use non-reserved PHP identifiers for namespace, section, module and method.');
            }
        }
        if (method_exists(\Wbengine\Box\WbengineBoxAbstract::class, $method)) { throw new \InvalidArgumentException('Choose an action that does not override inherited box methods.'); }
        $sectionKey = strtolower($section);
        if (!preg_match('/^[a-z][a-z0-9_]{0,44}$/D', $sectionKey)) { throw new \InvalidArgumentException('Use a section key of at most 45 characters.'); }
        $base = realpath($root);
        if ($base === false || !is_dir($base) || is_link($root)) { throw new \RuntimeException('Choose an existing application root directory.'); }
        $section = ucfirst($sectionKey); $module = ucfirst($module); $action = ucfirst($method);
        $files = [
            "Box/$section/$module.php" => "<?php\nnamespace $namespace\\Box\\$section;\n\nuse Wbengine\\Box\\WbengineBoxAbstract;\n\nclass $module extends WbengineBoxAbstract\n{\n    public function $action()\n    {\n        return \$this->getRenderer()->render('$section/$module/$action');\n    }\n}\n",
            "View/$section/$module/$action.tpl" => "<section>\n    <h1>$module</h1>\n</section>\n",
            "migrations/box-$section-$module-$action.sql" => "-- REVIEW ONLY: bind :page_link and review existing site/section records and tree positions.\n-- The supplied namespace must map to this root in Composer PSR-4.\n-- Standard cms_ prefix assumed. The page starts unpublished (visible=0).\n-- No SQL is executed by the generator. Back up before manual application.\nSTART TRANSACTION;\nINSERT INTO cms_sites (link, title, visible) VALUES (:page_link, '$module', 0);\nSET @wb_scaffold_site_id = LAST_INSERT_ID();\nINSERT INTO cms_sections (title, `key`, active) VALUES ('$section', '$sectionKey', 1);\nSET @wb_scaffold_section_id = LAST_INSERT_ID();\nINSERT INTO cms_boxes (section_id, module, method, sites, `order`)\nVALUES (@wb_scaffold_section_id, '$module', '$method', JSON_ARRAY(CAST(@wb_scaffold_site_id AS CHAR)), 0);\nCOMMIT;\n",
        ];
        foreach (array_keys($files) as $relative) { $this->validatePath($base, $relative); }
        if (!$write) { return $files; }
        $created = []; $directories = [];
        try {
            foreach ($files as $relative => $content) {
                $this->validatePath($base, $relative);
                $parts = explode('/', dirname($relative)); $directory = $base;
                foreach ($parts as $part) {
                    $directory .= '/' . $part;
                    if (!is_dir($directory)) {
                        if (!mkdir($directory, 0755)) { throw new \RuntimeException('Cannot create scaffold directory.'); }
                        $directories[] = $directory;
                    }
                    if (is_link($directory) || !str_starts_with(realpath($directory) . '/', $base . '/')) { throw new \RuntimeException('Scaffold directory escapes application root.'); }
                }
                $target = $base . '/' . $relative; $temporary = tempnam($directory, '.wb-scaffold-');
                if ($temporary === false) { throw new \RuntimeException('Cannot prepare scaffold file.'); }
                try {
                    if (file_put_contents($temporary, $content) !== strlen($content) || !chmod($temporary, 0644) || !@link($temporary, $target)) { throw new \RuntimeException('Cannot create scaffold file without overwriting existing work.'); }
                    $created[] = $target;
                } finally { unlink($temporary); }
            }
        } catch (\Throwable $error) {
            foreach (array_reverse($created) as $file) { unlink($file); }
            foreach (array_reverse($directories) as $directory) { @rmdir($directory); }
            throw $error;
        }
        return $files;
    }

    private function validatePath(string $base, string $relative): void
    {
        $path = $base;
        foreach (explode('/', $relative) as $part) {
            $path .= '/' . $part;
            if ($path !== $base . '/' . $relative && file_exists($path) && !is_dir($path)) { throw new \RuntimeException('Scaffold parent path is not a directory.'); }
            if (is_link($path)) { throw new \RuntimeException('Symlinks are not allowed in scaffold paths.'); }
        }
        if (file_exists($path)) { throw new \RuntimeException('Scaffold destination already exists.'); }
    }
}
