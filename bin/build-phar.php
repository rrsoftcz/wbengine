<?php
// Builds the single-file MCP server: php -d phar.readonly=0 bin/build-phar.php [output-directory]
// The archive holds the Wbengine\Mcp classes, the guide texts and the entry point, nothing else, and is signed with SHA-256.
// A file with the checksum is written next to it.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!Phar::canWrite()) {
    fwrite(STDERR, "Run with: php -d phar.readonly=0 bin/build-phar.php [output-directory]\n");
    exit(2);
}
$root = dirname(__DIR__);
$directory = rtrim($argv[1] ?? $root . '/build', '/');
if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
    fwrite(STDERR, "Cannot create $directory\n");
    exit(2);
}
$file = $directory . '/wbengine-mcp.phar';
@unlink($file);
$phar = new Phar($file, 0, 'wbengine-mcp.phar');
$phar->startBuffering();
$sources = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/Mcp', FilesystemIterator::SKIP_DOTS));
$paths = [];
foreach ($sources as $path) {
    if ($path->isFile() && ('php' === $path->getExtension() || 'md' === $path->getExtension())) { $paths[] = $path->getPathname(); }
}
$paths[] = $root . '/bin/wbengine-mcp.php';
sort($paths);
foreach ($paths as $path) {
    $phar->addFile($path, substr($path, strlen($root) + 1));
}
// The shebang of the entry point is for direct execution; the phar has its own stub.
$phar->setStub("#!/usr/bin/env php\n<?php\nPhar::mapPhar('wbengine-mcp.phar');\nrequire 'phar://wbengine-mcp.phar/bin/wbengine-mcp.php';\n__HALT_COMPILER();\n");
$phar->setSignatureAlgorithm(Phar::SHA256);
$phar->stopBuffering();
chmod($file, 0755);
$checksum = hash_file('sha256', $file);
file_put_contents($file . '.sha256', $checksum . '  wbengine-mcp.phar' . "\n");
fwrite(STDOUT, count($paths) . " files, " . filesize($file) . " bytes\n" . $checksum . "  " . $file . "\n");
