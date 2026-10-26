<?php

// Read-only inventory aid, not a compatibility certificate or a template validator.
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Wbengine\Template\Compiler;
use Wbengine\Template\Exception\TemplateException;

$scan = static function (string $root, bool $framework): array {
    if (!is_dir($root)) {
        throw new InvalidArgumentException('Template source directory does not exist: ' . $root);
    }
    $root = realpath($root);
    $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static fn ($file) => !$file->isLink() && (!$file->isDir() || !in_array($file->getFilename(),
            ['vendor', 'node_modules', '.git', 'Compiled', 'tests'], true))
    ));
    $result = [];
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'tpl') { continue; }
        $path = substr($file->getPathname(), strlen($root) + 1);
        $source = file_get_contents($file->getPathname());
        if ($source === false) { throw new RuntimeException('Cannot read template: ' . $path); }
        $entry = ['path' => $path, 'sha256' => hash('sha256', $source)];
        if ($framework && in_array($path, ['Exception/exception.tpl', 'Application/exception.tpl', 'Box/exception.tpl'], true)) {
            $result[$path] = $entry + ['consumer' => 'sprintf', 'compile' => 'not-applicable', 'status' => 'outside-renderer'];
            continue;
        }
        // Heuristics ignore literal/comment bodies; quoted content is not fully tokenized.
        $active = preg_replace('/\{\*.*?\*\}/s', '', $source);
        $active = preg_replace('/\{literal\}.*?\{\/literal\}/s', '', $active);
        preg_match_all('/\{\/?([A-Za-z_]\w*)(?=[\s=}])/', $active, $tags);
        preg_match_all('/(?<!\|)\|([A-Za-z_]\w*)/', $active, $modifiers);
        $hints = array_values(array_unique(array_diff($tags[1],
            ['if', 'elseif', 'else', 'foreach', 'foreachelse', 'include', 'set', 'cache', 't'])));
        sort($hints);
        $entry += ['consumer' => 'renderer', 'tags' => array_count_values($tags[1]),
            'modifiers' => array_count_values($modifiers[1]), 'reviewHints' => $hints];
        try {
            (new Compiler())->compile($source, $path);
            $entry += ['compile' => 'compiled', 'status' => $hints ? 'review' : 'compiled-only'];
        } catch (TemplateException $e) {
            $entry += ['compile' => 'failed', 'status' => 'review', 'diagnostic' => $e->getMessage()];
        }
        $result[$path] = $entry;
    }
    ksort($result);
    return array_values($result);
};
try {
    $report = ['framework' => $scan(dirname(__DIR__, 2), true)];
    if (isset($argv[1])) { $report['application'] = $scan($argv[1], false); }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
