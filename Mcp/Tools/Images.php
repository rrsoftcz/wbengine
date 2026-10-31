<?php

namespace Wbengine\Mcp\Tools;

use Wbengine\Mcp\Api\Client;
use Wbengine\Mcp\Tool;
use Wbengine\Mcp\ToolError;
use Wbengine\Mcp\ToolRegistry;
use Wbengine\Mcp\ToolResult;

/** Images for articles: upload a PNG, JPEG or WebP and get the Markdown that shows it. */
final class Images
{
    public const MAX_BYTES = 524288;
    private const TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];

    public function __construct(private readonly Client $api, private readonly SiteFacts $facts)
    {
    }

    public function register(ToolRegistry $tools): void
    {
        $tools->add(new Tool('upload_image', 'Upload an image', 'Uploads a PNG, JPEG or WebP image of at most 512 KiB (8192 pixels on a side) to the site and returns the address and the Markdown to paste into an article. Give path (a file on this computer) or base64 with mime. alt is required: it describes the picture for people who cannot see it and for search engines. The image becomes public at once.', ['type' => 'object', 'additionalProperties' => false, 'required' => ['alt'], 'properties' => [
            'alt' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200, 'description' => 'What the picture shows, in the language of the article.'],
            'path' => ['type' => 'string', 'maxLength' => 1024, 'description' => 'Path of an image file on this computer.'],
            'base64' => ['type' => 'string', 'maxLength' => 720000], 'mime' => ['type' => 'string', 'enum' => ['image/png', 'image/jpeg', 'image/webp']],
        ]], ['readOnly' => false, 'destructive' => false, 'idempotent' => false], fn(array $a) => Guard::run(fn() => $this->upload($a)), ['images:write']));
        $tools->add(new Tool('list_images', 'List uploaded images', 'Lists the uploaded images with their address, type and size.', ['type' => 'object', 'additionalProperties' => false, 'properties' => ['page' => ['type' => 'integer', 'minimum' => 1], 'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50]]], ['readOnly' => true, 'destructive' => false, 'idempotent' => true], fn(array $a) => Guard::run(fn() => $this->list($a)), ['images:read']));
    }

    private function upload(array $a): ToolResult
    {
        if (isset($a['path']) === isset($a['base64'])) { throw new ToolError('Give either path or base64 (with mime), not both and not neither.'); }
        if (isset($a['path'])) {
            [$bytes, $mime] = $this->readFile($a['path']);
        } else {
            $bytes = base64_decode($a['base64'], true);
            $mime = $a['mime'] ?? throw new ToolError('base64 needs mime.');
            if (false === $bytes || '' === $bytes) { throw new ToolError('The base64 text is not valid.'); }
        }
        if (strlen($bytes) > self::MAX_BYTES) { throw new ToolError('The image is ' . strlen($bytes) . ' bytes; the limit is 512 KiB (524288). Resize or compress it first.'); }
        $info = @getimagesizefromstring($bytes);
        if (!$info || ($info['mime'] ?? null) !== $mime) { throw new ToolError('The content is not a ' . $mime . ' image.'); }
        if ($info[0] > 8192 || $info[1] > 8192) { throw new ToolError('The image is ' . $info[0] . 'x' . $info[1] . ' pixels; the limit is 8192 on a side.'); }
        if (empty($this->facts->capabilities()['capabilities']['images']['write'])) { throw new ToolError('This site cannot store images yet: the administrator has to configure an image directory (WBENGINE_IMAGE_DIR).'); }
        $r = $this->api->request('POST', '/images', [], ['base64' => base64_encode($bytes), 'mime' => $mime]);
        $d = (array) $r->data;
        $url = (string) ($d['url'] ?? '');
        $alt = str_replace(['[', ']', "\n", "\r"], ' ', $a['alt']);
        return ToolResult::ok('Uploaded ' . $info[0] . 'x' . $info[1] . ' ' . $mime . ', ' . strlen($bytes) . ' bytes. Address: ' . $url . "\nMarkdown: ![" . $alt . '](' . $url . ')', ['image_id' => $d['image_id'] ?? null, 'url' => $url, 'markdown' => '![' . $alt . '](' . $url . ')', 'width' => $info[0], 'height' => $info[1], 'size' => strlen($bytes)]);
    }

    /** @return array{string, string} bytes and mime of a local image file */
    private function readFile(string $path): array
    {
        $real = realpath($path);
        $type = self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
        if (null === $type) { throw new ToolError('Only .png, .jpg, .jpeg and .webp files can be uploaded.'); }
        if (false === $real || !is_file($real) || !is_readable($real)) { throw new ToolError('The file does not exist or cannot be read: ' . $path); }
        $size = filesize($real);
        if (false === $size || $size > self::MAX_BYTES) { throw new ToolError('The file is larger than 512 KiB. Resize or compress it first.'); }
        $bytes = file_get_contents($real);
        if (false === $bytes) { throw new ToolError('The file could not be read.'); }
        return [$bytes, $type];
    }

    private function list(array $a): ToolResult
    {
        $r = $this->api->request('GET', '/images', array_filter(['page' => $a['page'] ?? null, 'per_page' => $a['per_page'] ?? null], static fn($v) => null !== $v));
        $items = array_map(static fn($i) => ['id' => $i['image_id'], 'url' => $i['url'], 'mime' => $i['mime'], 'size' => (int) $i['size']], (array) $r->data);
        $lines = array_map(static fn($i) => $i['url'] . '  ' . $i['mime'] . '  ' . $i['size'] . ' bytes', $items);
        return ToolResult::ok(($r->pagination['total'] ?? count($items)) . ' images.' . ([] === $lines ? '' : "\n" . implode("\n", $lines)), ['images' => $items, 'pagination' => $r->pagination]);
    }
}
