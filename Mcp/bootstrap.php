<?php
// Autoloader for the Wbengine\Mcp classes alone. Used by bin/wbengine-mcp.php and inside the phar,
// so the server needs neither Composer nor the rest of the framework.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Wbengine\\Mcp\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) { require $file; }
    }
});
