<?php
// Test router: all database state is connection-local and raster bytes live in a test directory.
require dirname(__DIR__) . '/bootstrap.php';
ini_set('error_log', '/dev/null');
use Wbengine\Db;

Db::query('CREATE TEMPORARY TABLE cms_api_images (image_id CHAR(32) PRIMARY KEY, mime VARCHAR(32), size INT, sha256 CHAR(64), integration_id INT, created_at BIGINT) ENGINE=InnoDB');
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
$directory = getenv('WBENGINE_IMAGE_DIR');
foreach ([str_repeat('1', 32), str_repeat('2', 32), str_repeat('3', 32)] as $id) { file_put_contents($directory . '/' . $id, $png); }
file_put_contents($directory . '/.upload-' . str_repeat('4', 32), $png);
foreach ([str_repeat('1', 32) => 'image/png', str_repeat('3', 32) => 'image/jpeg'] as $id => $mime) {
    Db::insert('cms_api_images', ['image_id' => $id, 'mime' => $mime, 'size' => strlen($png), 'sha256' => hash('sha256', $png), 'integration_id' => 1, 'created_at' => 100]);
}
(new \Wbengine\Api())->Initialize();
