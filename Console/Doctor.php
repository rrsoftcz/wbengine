<?php
namespace Wbengine\Console;

/** Setup/deploy diagnostics: never attached to an HTTP lifecycle. */
final class Doctor
{
    /** Schema of migrations/4.0.0/002, shared with the integration screen preflight. */
    public const SCHEMA_INTEGRATIONS = ['cms_api_integrations'=>['integration_id','name','key_hash','scopes','created_by','created_at','revoked_at'], 'cms_api_audit'=>['audit_id','integration_id','central_actor','local_user_id','action','outcome','created_at']];
    /** Schema of migrations/4.0.0/007: last_used_at of integrations. */
    public const SCHEMA_INTEGRATIONS_LAST_USED = ['cms_api_integrations'=>['last_used_at']];

    /** Schema of migrations/4.8.0/008: change log source, local user and audit detail. */
    public const SCHEMA_INTEGRATIONS_IPS = ['cms_api_integrations'=>['allowed_ips','last_used_ip']];
    public const SCHEMA_ARTICLE_HIDDEN = ['cms_articles'=>['hidden']];
    public const SCHEMA_CHANGE_LOG = ['cms_api_changes'=>['source','local_user_id'], 'cms_api_audit'=>['detail']];

    /** Schema of migrations/4.0.0/006: first-party analytics, shared with the integration screen preflight. */
    public const SCHEMA_ANALYTICS = ['cms_analytics_daily'=>['day','site_id','entity_type','entity_id','views'], 'cms_analytics_totals'=>['site_id','entity_type','entity_id','views'], 'cms_analytics_meta'=>['id','tracking_started_on']];

    public function check(array $config, string $root, bool $database = true, bool $inspectOptional = false): array
    {
        $report = [];
        $add = static function (string $name, bool $ok, string $action) use (&$report): void { $report[$name] = ['status'=>$ok ? 'ok' : 'fail', 'action'=>$ok ? '' : $action]; };
        $add('debug', ($config['debug'] ?? true) === false, 'Set debug to false for production.');
        $add('renderer.compileCheck', ($config['renderer']['compileCheck'] ?? true) === false, 'Set renderer.compileCheck to false; clear compiled templates explicitly during deploy.');
        $add('renderer.adapter', ($config['renderer']['adapterName'] ?? '') === 'NativeRenderer', 'Use NativeRenderer; install the separate Smarty compatibility package only for migrated legacy sites.');
        if (($config['recaptcha']['enabled'] ?? false) === true) { $add('secrets.recaptcha', !empty(getenv('WBENGINE_RECAPTCHA_SECRET') ?: ($config['recaptcha']['secretKey'] ?? null)), 'Set WBENGINE_RECAPTCHA_SECRET.'); }
        $add('opcache', extension_loaded('Zend OPcache') && filter_var(ini_get(PHP_SAPI === 'cli' ? 'opcache.enable_cli' : 'opcache.enable'), FILTER_VALIDATE_BOOL), 'Enable OPcache in the production web SAPI; CLI and web settings can differ.');
        $proxies = $config['http']['trustedProxies'] ?? []; $headers = $config['http']['forwardedHeaders'] ?? [];
        $add('http.proxy', is_array($proxies) && is_array($headers) && !array_filter($proxies, static function ($range) {
            if (!is_string($range)) { return true; }
            $parts = explode('/', $range);
            if (count($parts) > 2 || filter_var($parts[0], FILTER_VALIDATE_IP) === false) { return true; }
            $bits = strlen(inet_pton($parts[0])) * 8;
            return isset($parts[1]) && (!ctype_digit($parts[1]) || (int) $parts[1] < 1 || (int) $parts[1] > $bits);
        }) && !array_diff($headers, ['x-forwarded-for','x-forwarded-proto']) && (!$headers || $proxies), 'Configure explicit http.trustedProxies and supported forwardedHeaders only behind your trusted proxy.');
        $add('http.https', str_starts_with((string) ($config['i18n']['baseUrl'] ?? ''), 'https://'), 'Set i18n.baseUrl to the public HTTPS origin and verify TLS/proxy forwarding in the web SAPI.');
        // Application base may be project/src, with Composer installed at project/vendor.
        $composerRoot = $root;
        $project = dirname($root);
        if (!is_file($root . '/vendor/composer/installed.php') && is_file($project . '/composer.json')
            && is_file($project . '/vendor/composer/installed.php')) {
            $composerRoot = $project;
        }
        $composer = $composerRoot . '/vendor/composer/autoload_classmap.php';
        $map = is_file($composer) ? require $composer : [];
        $add('composer.optimized', is_array($map) && count($map) > 1, 'Run composer install --no-dev --optimize-autoloader in the application during deploy.');
        $installedFile = $composerRoot . '/vendor/composer/installed.php';
        $installed = is_file($installedFile) ? require $installedFile : [];
        $add('composer.production', isset($installed['root']['dev']) && $installed['root']['dev'] === false, 'Install the application with composer install --no-dev --optimize-autoloader during deploy.');
        $templates = $root . '/' . trim((string) ($config['renderer']['templatesDir'] ?? 'View'), '/');
        $compiled = $templates . '/' . trim((string) ($config['renderer']['compiledDir'] ?? 'Compiled'), '/');
        $add('writable.compiled', is_dir($compiled) && is_writable($compiled), 'Create renderer compiledDir under templatesDir and grant write access to the web user; verify as that user.');
        $cache = $config['cache'] ?? [];
        if ($cache['enabled'] ?? false) {
            $driver = strtolower((string) ($cache['driver'] ?? 'file'));
            $db = $config['dbAdapterDefinition'] ?? [];
            $prefix = 'wb' . substr(hash('sha256', ($db['hostname'] ?? '') . '/' . ($db['database'] ?? '')), 0, 8);
            $dir = $cache['dir'] ?? (sys_get_temp_dir() . '/wbengine-cache-' . $prefix);
            $ok = match ($driver) { 'file' => $this->canCreateCacheDirectory(rtrim((string) $dir, '/')), 'apcu' => function_exists('apcu_enabled') && apcu_enabled(), default => false };
            $add('cache.driver', $ok, 'Select file with a writable cache.dir or writable parent for creation (default: system temp/DB namespace), or enable APCu in the web SAPI.');
        } else { $report['cache.driver'] = ['status'=>'skip','action'=>'Cache disabled.']; }
        $requirements = [
            '002'=>[!empty($config['apiV2']['enabled']), self::SCHEMA_INTEGRATIONS],
            '003'=>[!empty($config['apiV2']['enabled']), ['cms_articles'=>['content_format'], 'cms_article_translations'=>['content_format']]],
            '004'=>[false, ['cms_content_migrations'=>['migration_id','entity','article_id','locale','source_format','before_data','after_data','source_hash','target_hash','created_at','rolled_back_at']]],
            '005'=>[!empty($config['apiV2']['enabled']), ['cms_boxes'=>['api_settings'], 'cms_api_changes'=>['change_id','resource','resource_id','action','before_data','after_data','integration_id','central_actor','created_at'], 'cms_api_drafts'=>['article_id','source_etag','payload','revision_id'], 'cms_api_revisions'=>['revision_id','article_id','payload','action','integration_id','central_actor','created_at'], 'cms_api_images'=>['image_id','mime','size','sha256','integration_id','created_at']]],
            '007'=>[!empty($config['apiV2']['enabled']), self::SCHEMA_INTEGRATIONS_LAST_USED],
            '008'=>[!empty($config['apiV2']['enabled']), self::SCHEMA_CHANGE_LOG],
            '011'=>[!empty($config['apiV2']['enabled']), self::SCHEMA_INTEGRATIONS_IPS],
            '012'=>[!empty($config['apiV2']['enabled']), self::SCHEMA_ARTICLE_HIDDEN],
        ];
        $databaseDown = false;
        foreach ($requirements as $number => [$enabled, $schema]) {
            if (!$enabled && !$inspectOptional) { $report['migration.' . $number] = ['status'=>'skip','action'=>'Optional feature disabled.']; continue; }
            if (!$database) { $report['migration.' . $number] = ['status'=>'skip','action'=>'Run doctor or migration:status to check the database.']; continue; }
            // An unreachable server makes mysqli warn; the report must stay clean machine readable output.
            set_error_handler(static fn (): bool => true);
            try { $ok = !$databaseDown && (new MigrationStatus())->inspect($schema, $number === '005'); }
            catch (\Throwable) { $ok = false; $databaseDown = true; }
            finally { restore_error_handler(); }
            if (!$enabled && !$ok) { $report['migration.' . $number] = ['status'=>'skip', 'action'=>'Optional schema missing or DB unavailable; feature disabled. Apply only after backup when needed.']; continue; }
            $add('migration.' . $number, $ok, "Review and apply optional migrations/" . ($number === '008' ? '4.8.0' : '4.0.0') . "/$number SQL manually after backup; verify DB connectivity. No DDL was executed.");
        }
        return $report;
    }

    private function canCreateCacheDirectory(string $directory): bool
    {
        if ($directory === '') { return false; }
        while (!file_exists($directory) && !is_link($directory)) {
            $parent = dirname($directory);
            if ($parent === $directory) { return false; }
            $directory = $parent;
        }
        return is_dir($directory) && is_writable($directory);
    }
}
