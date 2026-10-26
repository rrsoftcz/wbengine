<?php
namespace Wbengine\Api\V2;

/** Opaque raster assets, kept outside executable web roots and served as bytes only. */
final class ImageStore
{
    public const MAX_BYTES = 524288;
    private string $directory;

    public function __construct(?string $directory = null)
    {
        if (null === $directory) {
            $settings = \Wbengine\Config::$config;
            $configured = is_object($settings) ? ($settings->apiV2->imageDirectory ?? null) : null;
            $directory = getenv('WBENGINE_IMAGE_DIR') ?: $configured;
        }
        if (null !== $directory && (!is_string($directory) || !str_starts_with($directory, '/'))) { throw new \InvalidArgumentException('Image storage requires an absolute local directory.'); }
        $this->directory = rtrim($directory ?? '', '/');
    }

    public function configured(): bool { return '' !== $this->directory; }

    public function put(string $base64, string $mime): array
    {
        if (strlen($base64) > (int) ceil(self::MAX_BYTES / 3) * 4) { throw new Problem('image_too_large', 'Image exceeds the 512 KiB limit.', 413); }
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) { throw new Problem('unsupported_image', 'Only PNG, JPEG and WebP images are supported.', 415); }
        if (!preg_match('~^[A-Za-z0-9+/]*={0,2}$~D', $base64) || strlen($base64) % 4) { throw new Problem('invalid_image', 'Invalid base64 image.'); }
        $bytes = base64_decode($base64, true);
        if (false === $bytes || '' === $bytes || strlen($bytes) > self::MAX_BYTES) { throw new Problem('invalid_image', 'Invalid image.'); }
        $size = @getimagesizefromstring($bytes);
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!$size || $detected !== $mime || ($size['mime'] ?? null) !== $mime || $size[0] > 8192 || $size[1] > 8192 || $size[0] * $size[1] > 20000000 || preg_match('/<\?(?:php|=)/i', $bytes)) { throw new Problem('invalid_image', 'Image MIME or dimensions do not match supported raster content.', 415); }
        $complete = match ($mime) {
            'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1a\n") && str_ends_with($bytes, "\0\0\0\0IEND\xaeB\x60\x82"),
            'image/jpeg' => str_starts_with($bytes, "\xff\xd8") && str_ends_with($bytes, "\xff\xd9"),
            'image/webp' => str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' && (unpack('Vlength', substr($bytes, 4, 4))['length'] + 8) === strlen($bytes),
        };
        if (!$complete) { throw new Problem('invalid_image', 'Incomplete image or trailing data.', 415); }
        $root = $this->root();
        $id = bin2hex(random_bytes(16));
        $path = $root . '/' . $id;
        $staging = $root . '/.upload-' . bin2hex(random_bytes(16));
        $handle = @fopen($staging, 'xb');
        if (false === $handle) { throw new Problem('image_storage_unavailable', 'Image storage is unavailable.', 503); }
        try {
            if (strlen($bytes) !== fwrite($handle, $bytes) || !fflush($handle)) { throw new Problem('image_storage_unavailable', 'Image storage is unavailable.', 503); }
            chmod($staging, 0600);
            if (!rename($staging, $path)) { throw new Problem('image_storage_unavailable', 'Image storage is unavailable.', 503); }
        } catch (\Throwable $e) { @unlink($staging); @unlink($path); throw $e; }
        finally { fclose($handle); }
        return ['image_id' => $id, 'mime' => $mime, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
    }

    public function read(string $id): string
    {
        $this->id($id); $path = $this->root() . '/' . $id;
        if (is_link($path) || !is_file($path)) { throw new Problem('not_found', 'Image not found.', 404); }
        $bytes = file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);
        if (false === $bytes || strlen($bytes) > self::MAX_BYTES) { throw new Problem('image_storage_unavailable', 'Image storage is unavailable.', 503); }
        return $bytes;
    }

    public function remove(string $id): void
    {
        $this->id($id); $path = $this->root() . '/' . $id;
        if (is_file($path) && !is_link($path) && !unlink($path)) { throw new Problem('image_storage_unavailable', 'Image storage is unavailable.', 503); }
    }

    public function id(string $id): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) { throw new Problem('invalid_id', 'Invalid image identifier.'); }
    }

    private function root(): string
    {
        if (!$this->configured()) { throw new Problem('image_storage_unconfigured', 'Image storage must be configured before use.', 503); }
        if (is_link($this->directory) || (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true))) { throw new Problem('unsafe_image_storage', 'A private image storage directory is required.', 503); }
        $real = realpath($this->directory);
        $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '/__wbengine_no_document_root__');
        if (false === $real || !is_writable($real) || (false !== $documentRoot && ($real === $documentRoot || str_starts_with($real, rtrim($documentRoot, '/') . '/')))) { throw new Problem('unsafe_image_storage', 'Image storage must be outside the document root.', 503); }
        return $real;
    }
}
