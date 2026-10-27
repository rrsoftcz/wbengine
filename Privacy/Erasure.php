<?php

namespace Wbengine\Privacy;

final class Erasure
{
    private HandlerRegistry $registry;

    public function __construct(?HandlerRegistry $registry = null)
    {
        $this->registry = $registry ?? new HandlerRegistry([new DatabaseHandler()]);
    }

    public function inspect(int $userId): array
    {
        if ($userId < 1) {
            throw new \InvalidArgumentException('User ID must be a positive integer.');
        }

        $findings = $this->registry->inspect($userId);
        $actions = [];
        $blocked = false;
        foreach ($findings as $finding) {
            $actions = array_replace($actions, $finding['actions']);
            $blocked = $blocked || $finding['blocked'];
        }

        return [
            'user_id' => $userId,
            'blocked' => $blocked,
            'actions' => $actions,
            'manual_review' => [
                'custom_application_tables',
                'content_authorship_and_uploaded_media',
                'web_server_and_database_logs',
                'database_backups',
                'google_analytics',
            ],
            'confirmation' => $this->confirmation($userId, $findings, $blocked),
        ];
    }

    public function erase(int $userId, string $confirmation): string
    {
        if ($userId < 1) {
            throw new \InvalidArgumentException('User ID must be a positive integer.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/D', $confirmation)) {
            throw new \InvalidArgumentException('A valid 64-character confirmation hash is required.');
        }

        $findings = $this->registry->inspect($userId);
        $actions = [];
        $blocked = false;
        $fingerprints = [];
        foreach ($findings as $name => $finding) {
            $actions = array_replace($actions, $finding['actions']);
            $blocked = $blocked || $finding['blocked'];
            $fingerprints[$name] = $finding['fingerprint'];
        }
        $reportConfirmation = $this->confirmation($userId, $findings, $blocked);
        if (0 === array_sum(array_map(static fn ($action) => $action['records'], $actions))) {
            return 'already_erased';
        }
        if ($blocked) {
            throw new \RuntimeException('The last administrator cannot be erased.');
        }
        if (!hash_equals($reportConfirmation, $confirmation)) {
            throw new \RuntimeException('The inspection has changed. Run privacy:inspect again.');
        }

        $this->registry->erase($userId, $fingerprints);
        return 'erased';
    }

    private function confirmation(int $userId, array $findings, bool $blocked): string
    {
        $snapshot = [];
        foreach ($findings as $name => $finding) {
            $snapshot[$name] = $finding['fingerprint'];
        }
        ksort($snapshot);
        return hash('sha256', json_encode(['user_id' => $userId, 'findings' => $snapshot, 'blocked' => $blocked], JSON_THROW_ON_ERROR));
    }
}
