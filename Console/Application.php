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
            $users = ['user:create'=>['email','username','role'], 'user:password'=>['email'], 'user:list'=>[], 'user:delete'=>['email','example','dry-run']];
            if (isset($users[$command]) && array_intersect(array_keys($options), ['password','passwd','pass','pwd','pw','secret'])) {
                throw new CommandException('Passwords are never accepted as arguments: they would leak through shell history and the process list. Enter the password at the prompt or pipe one line on standard input.');
            }
            $audit = ['audit:prune'=>['days','dry-run'], 'audit:list'=>['limit','resource','actor']];
            $install = ['integrations:install'=>['section','link','apply']];
            $allowed = $command === 'generate:box' ? ['root','namespace','section','module','method','write'] : array_merge(['config','root'], $users[$command] ?? [], $audit[$command] ?? [], $install[$command] ?? []);
            if (array_diff(array_keys($options), $allowed)) { throw new \InvalidArgumentException('Unknown option.'); }
            if ($command === 'help') {
                echo "doctor|config:check|migration:status|analytics:prune --config=/path/Settings.json [--root=/application]\naudit:prune [--days=N] [--dry-run] --config=/path/Settings.json [--root=/application]\naudit:list [--limit=N] [--resource=NAME] [--actor=ACTOR] --config=/path/Settings.json [--root=/application]\naudit:prune deletes audit, change log and revision rows older than audit.retentionDays and audit.revisionRetentionDays (default 365, 30 to 3650); --days overrides both. audit:list prints recent changes without their before/after data.\nuser:create --email=EMAIL [--username=NAME] [--role=user|editor|admin] --config=/path/Settings.json [--root=/application]\nuser:password --email=EMAIL --config=/path/Settings.json [--root=/application]\nuser:list --config=/path/Settings.json [--root=/application]\nuser:delete --email=EMAIL|--example [--dry-run] --config=/path/Settings.json [--root=/application]\nuser:* commands read the password from a no-echo prompt on a terminal, or one line from standard input; never from an argument.\nintegrations:install [--section=central] [--link=/integrations/] [--apply] --config=/path/Settings.json\nintegrations:install connects the built-in Integrations box to a page (section, page and box record; no application class is needed). It only previews until --apply is given, never changes existing records, and lists what still blocks issuing keys (exit code 1).\ngenerate:box --root=/application --namespace=App --section=Main --module=Welcome --method=index [--write]\nExit codes: 0 healthy/success, 1 failed checks, 2 invalid input. Generator defaults to preview; SQL requires manual review.\nProduction deploy: composer install --no-dev --optimize-autoloader; disable debug and renderer.compileCheck; clear the configured compiled template directory during deploy, then warm caches. Verify web-SAPI OPcache, web-user permissions, trusted proxies and HTTPS separately.\n";
                return 0;
            }
            if ($command === 'generate:box') {
                foreach (['root','namespace','section','module','method'] as $key) { if (!isset($options[$key]) || !is_string($options[$key])) { throw new \InvalidArgumentException('Missing generator option.'); } }
                if (isset($options['write']) && $options['write'] !== true) { throw new \InvalidArgumentException('Use bare --write to enable writes.'); }
                $files = (new BoxGenerator())->generate($options['root'], $options['namespace'], $options['section'], $options['module'], $options['method'], isset($options['write']));
                echo json_encode(['written'=>isset($options['write']), 'files'=>$files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                return 0;
            }
            if (!in_array($command, ['doctor','config:check','migration:status','analytics:prune','audit:prune','audit:list','integrations:install','user:create','user:password','user:list','user:delete'], true)) { throw new \InvalidArgumentException('Unknown command; run help.'); }
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
            if ('integrations:install' === $command) {
                foreach (['section', 'link'] as $key) { if (isset($options[$key]) && !is_string($options[$key])) { throw new \InvalidArgumentException('Use --name=value options.'); } }
                if (isset($options['apply']) && true !== $options['apply']) { throw new \InvalidArgumentException('Use bare --apply to write.'); }
                $apiV2 = $raw['apiV2'] ?? null;
                set_error_handler(static fn (): bool => true);
                try {
                    $result = (new IntegrationsInstaller())->install($options['section'] ?? 'central', $options['link'] ?? '/integrations/', isset($options['apply']), is_array($apiV2) && true === ($apiV2['enabled'] ?? false));
                } finally { restore_error_handler(); }
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                return [] === $result['problems'] ? 0 : 1;
            }
            if (isset($audit[$command])) {
                $result = $this->audit($command, $options);
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                return 0;
            }
            if (isset($users[$command])) {
                if (isset($options['root']) && (!is_string($options['root']) || !is_dir($options['root']))) { throw new \InvalidArgumentException('Supply an existing application --root directory.'); }
                if (!defined('S_TABLE_USERS')) { require_once dirname(__DIR__) . '/Application/Env/Const.php'; }
                $commands = new UserCommands();
                // An unreachable server makes mysqli warn; keep stderr free of connection details.
                set_error_handler(static fn (): bool => true);
                try {
                    $result = match ($command) { 'user:create' => $commands->create($options), 'user:password' => $commands->password($options), 'user:list' => $commands->list(), 'user:delete' => $commands->delete($options) };
                } finally { restore_error_handler(); }
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                return 0;
            }
            $root = $options['root'] ?? dirname(dirname(realpath($options['config'])));
            if (!is_string($root) || !is_dir($root)) { throw new \InvalidArgumentException('Supply an existing application --root directory.'); }
            $report = (new Doctor())->check($raw, $root, $command !== 'config:check', $command === 'migration:status');
            if ($command === 'migration:status') { $report = array_filter($report, static fn ($key) => str_starts_with($key, 'migration.'), ARRAY_FILTER_USE_KEY); }
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            return in_array('fail', array_column($report, 'status'), true) ? 1 : 0;
        } catch (CommandException $e) {
            fwrite(STDERR, 'Command failed: ' . $e->getMessage() . "\n");
            return 2;
        } catch (\Throwable) {
            // Third party config/database exceptions can contain credentials or SQL payloads.
            fwrite(STDERR, "Command failed: validate the command options, JSON configuration and destination permissions. Run help for usage. No secret values are printed.\n");
            return 2;
        }
    }

    /** audit:prune and audit:list. Options are validated here, the work lives in Wbengine\Audit. */
    private function audit(string $command, array $options): array
    {
        $integer = static function (string $name, int $min, int $max) use ($options): ?int {
            if (!isset($options[$name])) { return null; }
            if (!is_string($options[$name]) || !preg_match('/^[0-9]{1,5}$/D', $options[$name]) || (int) $options[$name] < $min || (int) $options[$name] > $max) {
                throw new CommandException("--$name must be an integer from $min to $max.");
            }
            return (int) $options[$name];
        };
        // An unreachable server makes mysqli warn; keep stderr free of connection details.
        set_error_handler(static fn (): bool => true);
        try {
            if ('audit:list' === $command) {
                foreach (['resource', 'actor'] as $name) {
                    if (isset($options[$name]) && (!is_string($options[$name]) || '' === $options[$name] || strlen($options[$name]) > 128)) { throw new CommandException("--$name needs a value of at most 128 characters."); }
                }
                return ['changes' => \Wbengine\Audit\ChangeLog::recent($integer('limit', 1, 500) ?? 20, $options['resource'] ?? null, $options['actor'] ?? null)];
            }
            if (isset($options['dry-run']) && true !== $options['dry-run']) { throw new CommandException('Use bare --dry-run without a value.'); }
            $dry = isset($options['dry-run']);
            $days = $integer('days', 30, 3650);
            $result = (new \Wbengine\Audit\Retention())->prune($days, null, $dry);
            if (!$dry) {
                \Wbengine\Audit\ChangeLog::recordQuietly('audit', 'prune', 'prune', null, ['audit' => $result['audit'], 'changes' => $result['changes'], 'revisions' => $result['revisions'], 'audit_days' => $result['audit_days'], 'revision_days' => $result['revision_days']], \Wbengine\Audit\ChangeLog::cliActor(), 'cli');
            }
            return $result;
        } finally { restore_error_handler(); }
    }
}
