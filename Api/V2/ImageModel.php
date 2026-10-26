<?php
namespace Wbengine\Api\V2;

use Wbengine\Db;

/** Image metadata and audit are atomic; orphan bytes never have a public metadata entry. */
class ImageModel extends ContentModel
{
    private ImageStore $store;
    public function __construct(?ImageStore $store = null, ?callable $flusher = null)
    {
        parent::__construct(null, $flusher);
        $this->store = $store ?? new ImageStore();
    }

    public function configured(): bool { return $this->store->configured(); }

    public function images(array $query): array
    {
        $page = Contract::pagination($query);
        $rows = Db::fetchAllAssoc('SELECT * FROM cms_api_images ORDER BY image_id LIMIT ? OFFSET ?', [$page['per_page'], $page['offset']]);
        foreach ($rows as &$row) { $row = $this->metadata($row); } unset($row);
        $total = (int) Db::fetchAssoc('SELECT COUNT(*) AS n FROM cms_api_images')['n'];
        return Contract::page($rows, $query, $total);
    }

    public function image(string $id, bool $content = false): array
    {
        $row = $this->imageRow($id);
        $bytes = $this->verifiedBytes($row);
        $data = $this->metadata($row);
        if ($content) { $data['base64'] = base64_encode($bytes); }
        return ['data' => $data, 'etag' => Resources::etag($row)];
    }

    public function media(string $id): array
    {
        $row = $this->imageRow($id);
        return ['bytes' => $this->verifiedBytes($row), 'mime' => $row['mime'], 'etag' => Resources::etag($row)];
    }

    public function upload(array $data, array $actor): array
    {
        if (array_diff(array_keys($data), ['base64', 'mime']) || !isset($data['base64'], $data['mime']) || !is_string($data['base64']) || !is_string($data['mime'])) { throw new Problem('invalid_image', 'base64 and mime are required strings.'); }
        $connection = $this->beginOwnedTransaction(['cms_api_images', 'cms_api_changes', self::AUDIT_TABLE]);
        $image = null;
        try {
            $image = $this->store->put($data['base64'], $data['mime']);
            $values = $image + ['integration_id' => $actor['integration_id'], 'created_at' => time()];
            Db::insert('cms_api_images', $values);
            $row = $this->imageRow($image['image_id'], true);
            $this->verifyStored($row, $values);
            $this->change('images', $image['image_id'], 'create', null, $row, $actor);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollback();
            if (null !== $image) { $this->store->remove($image['image_id']); }
            throw $e;
        }
        ($this->flusher)();
        return ['data' => $this->metadata($row), 'etag' => Resources::etag($row)];
    }

    public function deleteImage(string $id, ?string $etag, array $actor): array
    {
        $connection = $this->beginOwnedTransaction(['cms_api_images', 'cms_api_changes', self::AUDIT_TABLE]);
        try {
            $row = $this->imageRow($id, true);
            Resources::match($etag, $row);
            Db::query('DELETE FROM cms_api_images WHERE image_id = ?', [$id]);
            $this->change('images', $id, 'delete', $row, null, $actor);
            $connection->commit();
        } catch (\Throwable $e) { $connection->rollback(); throw $e; }
        // Once metadata is removed, these bytes cannot be served even if cleanup fails.
        try { $this->store->remove($id); }
        catch (\Throwable $e) { error_log(__METHOD__ . ': deferred image byte cleanup required.'); }
        ($this->flusher)();
        return ['data' => ['deleted' => true, 'image_id' => $id]];
    }

    private function imageRow(string $id, bool $lock = false): array
    {
        $this->store->id($id);
        $row = Db::fetchAssoc('SELECT * FROM cms_api_images WHERE image_id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id]);
        if (!$row) { throw new Problem('not_found', 'Image not found.', 404); }
        return $row;
    }

    private function verifiedBytes(array $row): string
    {
        $bytes = $this->store->read($row['image_id']);
        if (strlen($bytes) !== $row['size'] || !hash_equals($row['sha256'], hash('sha256', $bytes)) || !in_array($row['mime'], ['image/png', 'image/jpeg', 'image/webp'], true) || (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== $row['mime'] || ((@getimagesizefromstring($bytes))['mime'] ?? null) !== $row['mime']) { throw new Problem('image_storage_mismatch', 'Image storage integrity check failed.', 503); }
        return $bytes;
    }

    private function metadata(array $row): array
    {
        return array_intersect_key($row, array_flip(['image_id', 'mime', 'size', 'sha256', 'created_at'])) + ['url' => '/api/v2/media/' . $row['image_id'], 'public' => true];
    }
}
