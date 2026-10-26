<?php
namespace Wbengine\Content\Migration;

use Wbengine\Application\Path\File;
use Wbengine\Config;

/** Thin administrator entry point; conversion and persistence stay in the content object tree. */
final class Cli
{
    public function options(array $arguments): array
    {
        $out = [];
        $allowed = ['config', 'article', 'locale', 'source-format', 'apply', 'approve', 'rollback', 'texy-autoload', 'articles-table', 'translations-table', 'ledger-table'];
        foreach ($arguments as $argument) {
            if ($argument === '--help') { return ['mode' => 'help']; }
            if ($argument === '--dry-run') { $out['dry-run'] = true; continue; }
            if (!preg_match('/^--([a-z-]+)=(.+)$/D', $argument, $m) || !in_array($m[1], $allowed, true) || isset($out[$m[1]])) { throw new \InvalidArgumentException('Unknown, duplicate or malformed migration option'); }
            $out[$m[1]] = $m[2];
        }
        $out['mode'] = isset($out['apply']) ? 'apply' : (isset($out['rollback']) ? 'rollback' : 'dry-run');
        if ((isset($out['apply']) && isset($out['rollback'])) || (isset($out['dry-run']) && $out['mode'] !== 'dry-run')) { throw new \InvalidArgumentException('Choose exactly one migration mode'); }
        if (!isset($out['config'])) { throw new \InvalidArgumentException('Provide --config=/absolute/path/Settings.json'); }
        if ($out['mode'] === 'dry-run' && (!isset($out['article'], $out['source-format']) || !ctype_digit($out['article']) || (int) $out['article'] < 1 || !in_array($out['source-format'], ['html', 'texy'], true))) { throw new \InvalidArgumentException('Dry-run requires --article=ID and explicit --source-format=html or texy'); }
        if ($out['mode'] === 'apply' && (!isset($out['approve']) || !preg_match('/^[a-f0-9]{64}$/D', $out['approve']))) { throw new \InvalidArgumentException('Apply requires --approve=SHA256 from the reviewed dry-run plan'); }
        if ($out['mode'] === 'rollback' && !preg_match('/^[a-f0-9]{64}$/D', $out['rollback'])) { throw new \InvalidArgumentException('Rollback requires the migration SHA256 id'); }
        return $out;
    }
    public function run(array $arguments): int
    {
        try {
            $options = $this->options($arguments);
            if ($options['mode'] === 'help') { fwrite(STDOUT, $this->help()); return 0; }
            $config = realpath($options['config']);
            if ($config === false || !is_file($config)) { throw new \InvalidArgumentException('Configuration file not found'); }
            Config::load(new File($config, true));
            $model = new Model($options['articles-table'] ?? 'cms_articles', $options['translations-table'] ?? 'cms_article_translations', $options['ledger-table'] ?? 'cms_content_migrations');
            $plan = null;
            if ($options['mode'] === 'apply') {
                $report = $this->readReport($options['apply']);
                $plans = isset($report['approval_hash']) ? [$report] : ($report['plans'] ?? []);
                foreach ($plans as $candidate) { if (($candidate['approval_hash'] ?? null) === $options['approve']) { if ($plan !== null) { throw new \InvalidArgumentException('Approval hash occurs more than once'); } $plan = $candidate; } }
                if ($plan === null) { throw new \InvalidArgumentException('Reviewed approval hash not found in the report'); }
            }
            $format = $plan['source_format'] ?? ($options['source-format'] ?? 'html');
            if ($format === 'texy' && isset($options['texy-autoload'])) {
                $autoload = realpath($options['texy-autoload']);
                if ($autoload === false || !is_file($autoload)) { throw new \InvalidArgumentException('Optional Texy autoloader not found'); }
                require_once $autoload;
            }
            $migrator = new Migrator($model, Converters::for($format));
            $result = match ($options['mode']) {
                'apply' => ['mode' => 'apply', 'migration_id' => $migrator->apply($plan, $options['approve'])],
                'rollback' => ['mode' => 'rollback', 'migration_id' => $options['rollback'], 'changed' => $migrator->rollback($options['rollback'])],
                default => ['mode' => 'dry-run', 'plans' => isset($options['locale']) ? [$migrator->plan('translation', (int) $options['article'], $options['locale'], $format)] : $migrator->planArticle((int) $options['article'], $format)],
            };
            $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (strlen($json) > 16777216) { throw new \LengthException('Migration report exceeds 16 MiB; request one locale at a time'); }
            fwrite(STDOUT, $json . "\n");
            return 0;
        } catch (\Throwable $e) { fwrite(STDERR, 'Content migration: ' . $e->getMessage() . "\n"); return 1; }
    }
    private function readReport(string $path): array
    {
        $real = realpath($path);
        if ($real === false || !is_file($real)) { throw new \InvalidArgumentException('Reviewed report file not found'); }
        $handle = fopen($real, 'rb');
        try { $json = stream_get_contents($handle, 16777217); } finally { fclose($handle); }
        if (strlen($json) > 16777216) { throw new \LengthException('Reviewed migration report exceeds 16 MiB; split it into smaller batches'); }
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
    private function help(): string
    {
        return "Dry-run (default): php bin/migrate-content.php --config=/site/Config/Settings.json --article=42 --source-format=html\n"
            . "Include a single translation: add --locale=cs; otherwise the report includes the article and all translations.\n"
            . "Apply one reviewed safe plan: --config=... --apply=report.json --approve=SHA256\n"
            . "Rollback with conflict checking: --config=... --rollback=SHA256\n"
            . "Explicit Texy input: --source-format=texy requires optional compat/texy-migration. No source-format guessing.\n"
            . "Apply requires migrations 003 and 004, transactional InnoDB tables and no surrounding transaction.\n";
    }
}
