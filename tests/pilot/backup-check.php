<?php
/** Local-only comparison of one fixed pilot source and its disposable restored copy. */
require __DIR__ . '/support.php';
const PILOT_BACKUP_SOURCE = 'wbengine_api_pilot';
const PILOT_BACKUP_RESTORE = 'wbengine_api_pilot_restore';
function pilotBackupIdentifier(string $identifier): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $identifier)) { throw new RuntimeException('Invalid pilot schema identifier.'); }
    return '`' . $identifier . '`';
}
function pilotBackupTables(mysqli $connection, string $schema): array
{
    return array_column($connection->execute_query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME', [$schema, 'BASE TABLE'])->fetch_all(MYSQLI_ASSOC), 'TABLE_NAME');
}
function pilotBackupDigest(mysqli $connection, string $schema, string $table): array
{
    $qualified = pilotBackupIdentifier($schema) . '.' . pilotBackupIdentifier($table);
    $definition = $connection->query('SHOW CREATE TABLE ' . $qualified)->fetch_assoc()['Create Table'];
    $encoded = [];
    $result = $connection->query('SELECT * FROM ' . $qualified);
    while ($row = $result->fetch_assoc()) { ksort($row, SORT_STRING); $encoded[] = serialize($row); }
    sort($encoded, SORT_STRING);
    $digest = hash_init('sha256');
    foreach ($encoded as $row) { hash_update($digest, strlen($row) . ':' . $row); }
    return ['rows' => count($encoded), 'schema_sha256' => hash('sha256', $definition), 'data_sha256' => hash_final($digest)];
}
try {
    $action = $argv[1] ?? 'compare';
    if (!in_array($action, ['prepare', 'compare', 'cleanup'], true)) { throw new InvalidArgumentException('Unknown pilot backup action.'); }
    $connection = new mysqli('db', 'root', pilotSecret('PILOT_DB_ROOT_PASSWORD'));
    $connection->set_charset('utf8mb4');
    $source = $connection->execute_query('SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [PILOT_BACKUP_SOURCE])->fetch_assoc();
    if (!$source) { throw new RuntimeException('The fixed isolated source schema is unavailable.'); }
    if ('cleanup' === $action) {
        $connection->query('DROP DATABASE IF EXISTS ' . pilotBackupIdentifier(PILOT_BACKUP_RESTORE));
        exit;
    }
    if ('prepare' === $action) {
        $connection->query('DROP DATABASE IF EXISTS ' . pilotBackupIdentifier(PILOT_BACKUP_RESTORE));
        $connection->query('CREATE DATABASE ' . pilotBackupIdentifier(PILOT_BACKUP_RESTORE) . ' CHARACTER SET ' . pilotBackupIdentifier($source['DEFAULT_CHARACTER_SET_NAME']) . ' COLLATE ' . pilotBackupIdentifier($source['DEFAULT_COLLATION_NAME']));
        exit;
    }
    $restored = $connection->execute_query('SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [PILOT_BACKUP_RESTORE])->fetch_assoc();
    if ($source !== $restored) { throw new RuntimeException('Restored database defaults differ.'); }
    $tables = pilotBackupTables($connection, PILOT_BACKUP_SOURCE);
    if (!$tables || $tables !== pilotBackupTables($connection, PILOT_BACKUP_RESTORE)) { throw new RuntimeException('Restored table names or counts differ.'); }
    $manifest = ['source' => PILOT_BACKUP_SOURCE, 'restored' => PILOT_BACKUP_RESTORE, 'tables' => [], 'table_count' => count($tables), 'row_count' => 0];
    foreach ($tables as $table) {
        $before = pilotBackupDigest($connection, PILOT_BACKUP_SOURCE, $table);
        $after = pilotBackupDigest($connection, PILOT_BACKUP_RESTORE, $table);
        if ($before !== $after) { throw new RuntimeException('Restored schema or data digest differs.'); }
        $manifest['tables'][$table] = $before;
        $manifest['row_count'] += $before['rows'];
    }
    pilotWrite('/state/backup-result.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    echo 'Backup/restore verified: ' . $manifest['table_count'] . ' tables, ' . $manifest['row_count'] . " rows; schema and data hashes match.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Pilot backup verification failed (' . get_class($error) . "). No credentials or row data were logged.\n");
    exit(1);
}
