<?php
/** Shared CLI-only helpers for the isolated local pilot. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
require_once '/app/vendor/autoload.php';
require_once '/app/Application/Env/Const.php';

function pilotSecret(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/D', $value)) { throw new RuntimeException('Pilot bootstrap secret is missing.'); }
    return $value;
}
function pilotWrite(string $path, string $contents, int $mode = 0600): void
{
    $staging = $path . '.new';
    if (strlen($contents) !== file_put_contents($staging, $contents, LOCK_EX)) { throw new RuntimeException('Pilot state write failed.'); }
    chmod($staging, $mode);
    if (!rename($staging, $path)) { throw new RuntimeException('Pilot state replacement failed.'); }
}
function pilotInstallation(string $installation): array
{
    return match ($installation) {
        'first' => ['database' => 'wbengine_api_pilot', 'user' => 'pilot_api', 'secret' => 'PILOT_DB_API_PASSWORD', 'app' => '/state/app', 'seed' => '/state/seed.json', 'hostname' => 'api.wbengine.test', 'label' => 'pilot'],
        'second' => ['database' => 'wbengine_api_pilot_second', 'user' => 'pilot_api_second', 'secret' => 'PILOT_DB_SECOND_PASSWORD', 'app' => '/state/app2', 'seed' => '/state/seed-second.json', 'hostname' => 'api2.wbengine.test', 'label' => 'second pilot'],
        default => throw new InvalidArgumentException('Unknown isolated pilot installation.'),
    };
}
function pilotConsumerEnvironment(): void
{
    $first = json_decode(file_get_contents('/state/seed.json'), true, 64, JSON_THROW_ON_ERROR);
    $second = json_decode(file_get_contents('/state/seed-second.json'), true, 64, JSON_THROW_ON_ERROR);
    $proxy = trim(file_get_contents('/state/proxy-ip.txt'));
    if (!filter_var($proxy, FILTER_VALIDATE_IP)) { throw new RuntimeException('Pilot proxy address is unavailable.'); }
    $values = [
        'PILOT_API_URL' => 'https://api.wbengine.test/', 'PILOT_API_KEY' => $first['key'],
        'PILOT_SECOND_API_URL' => 'https://api2.wbengine.test/', 'PILOT_SECOND_API_KEY' => $second['key'],
        'DATABASE_URL' => 'mysql://pilot_admin:' . pilotSecret('PILOT_DB_ADMIN_PASSWORD') . '@db:3306/wbengine_admin_pilot',
        'MASTER_KEY' => pilotSecret('PILOT_MASTER_KEY'), 'REMOTE_IP_ALLOWLIST' => $proxy,
        'NODE_EXTRA_CA_CERTS' => '/pilot-runtime/ca.crt',
        'PILOT_ARTICLE_ID' => '1', 'PILOT_PAGE_ID' => '1', 'PILOT_SECTION_ID' => '1',
    ];
    $lines = '';
    foreach ($values as $name => $value) {
        if (str_contains($value, "\n") || str_contains($value, "\r")) { throw new RuntimeException('Invalid generated pilot environment.'); }
        $lines .= $name . '=' . $value . "\n";
    }
    pilotWrite('/state/consumer.env', $lines);
}
