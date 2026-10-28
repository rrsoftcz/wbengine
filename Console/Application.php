<?php
namespace Wbengine\Console;

use Wbengine\Application\Path\File;
use Wbengine\Config;

final class Application
{
    public function run(array $arguments): int
    {
        try {
            $command = $arguments[1] ?? 'help'; $options = [];
            foreach (array_slice($arguments, 2) as $argument) {
                if (!preg_match('/^--([a-z-]+)(?:=(.*))?$/D', $argument, $match)) { throw new \InvalidArgumentException('Use --name=value options.'); }
                if (isset($options[$match[1]])) { throw new \InvalidArgumentException('Duplicate option.'); }
                $options[$match[1]] = $match[2] ?? true;
            }
            $allowed = $command === 'generate:box' ? ['root','namespace','section','module','method','write'] : ['config','root'];
            if (array_diff(array_keys($options), $allowed)) { throw new \InvalidArgumentException('Unknown option.'); }
            if ($command === 'help') {
                echo "doctor|config:check|migration:status|analytics:prune --config=/path/Settings.json [--root=/application]\ngenerate:box --root=/application --namespace=App --section=Main --module=Welcome --method=index [--write]\nExit codes: 0 healthy/success, 1 failed checks, 2 invalid input. Generator defaults to preview; SQL requires manual review.\nProduction deploy: composer install --no-dev --optimize-autoloader; disable debug and renderer.compileCheck; clear the configured compiled template directory during deploy, then warm caches. Verify web-SAPI OPcache, web-user permissions, trusted proxies and HTTPS separately.\n";
                return 0;
            }
            if ($command === 'generate:box') {
                foreach (['root','namespace','section','module','method'] as $key) { if (!isset($options[$key]) || !is_string($options[$key])) { throw new \InvalidArgumentException('Missing generator option.'); } }
                if (isset($options['write']) && $options['write'] !== true) { throw new \InvalidArgumentException('Use bare --write to enable writes.'); }
                $files = (new BoxGenerator())->generate($options['root'], $options['namespace'], $options['section'], $options['module'], $options['method'], isset($options['write']));
                echo json_encode(['written'=>isset($options['write']), 'files'=>$files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                return 0;
            }
            if (!in_array($command, ['doctor','config:check','migration:status','analytics:prune'], true)) { throw new \InvalidArgumentException('Unknown command; run help.'); }
            if (!isset($options['config']) || !is_string($options['config']) || !is_file($options['config']) || !is_readable($options['config'])) { throw new \InvalidArgumentException('Supply a readable --config=/path/Settings.json.'); }
            if (strtolower(pathinfo($options['config'], PATHINFO_EXTENSION)) !== 'json') { throw new \InvalidArgumentException('The existing Config loader requires a JSON configuration.'); }
            $raw = json_decode(file_get_contents($options['config']), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($raw) || array_is_list($raw)) { throw new \InvalidArgumentException('Configuration must be a JSON object.'); }
            Config::load(new File($options['config']));
            if ($command === 'analytics:prune') {
                $deleted = (new \Wbengine\Analytics\Maintenance())->prune();
                echo json_encode(['deleted_daily_rows' => $deleted, 'retention_days' => Config::getAnalyticsSettings()['retentionDays']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                return 0;
            }
            $root = $options['root'] ?? dirname(dirname(realpath($options['config'])));
            if (!is_string($root) || !is_dir($root)) { throw new \InvalidArgumentException('Supply an existing application --root directory.'); }
            $report = (new Doctor())->check($raw, $root, $command !== 'config:check', $command === 'migration:status');
            if ($command === 'migration:status') { $report = array_filter($report, static fn ($key) => str_starts_with($key, 'migration.'), ARRAY_FILTER_USE_KEY); }
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            return in_array('fail', array_column($report, 'status'), true) ? 1 : 0;
        } catch (\Throwable) {
            // Third party config/database exceptions can contain credentials or SQL payloads.
            fwrite(STDERR, "Command failed: validate the command options, JSON configuration and destination permissions. Run help for usage. No secret values are printed.\n");
            return 2;
        }
    }
}
