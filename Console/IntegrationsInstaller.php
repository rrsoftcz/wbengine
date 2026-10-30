<?php
namespace Wbengine\Console;

use Wbengine\Api\V2\Preflight;
use Wbengine\Db;

/**
 * integrations:install. Makes the built-in Integrations box (Wbengine\Box\Integrations) reachable on a site:
 * the thin application class, the page, the section and the box record. Existing records and files are never
 * changed. Without --apply nothing is written, the result only lists what would be created.
 */
final class IntegrationsInstaller
{
    public const MODULE = 'integrations';
    public const METHOD = 'integrations';

    /** @return array{applied: bool, actions: array<int, array{what: string, status: string}>, problems: string[]} */
    public function install(string $root, string $namespace, string $sectionKey, string $link, bool $apply, bool $apiEnabled): array
    {
        foreach (explode('\\', $namespace) as $identifier) {
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $identifier)) { throw new \InvalidArgumentException('Use a valid PHP namespace.'); }
        }
        if (!preg_match('/^[a-z][a-z0-9_]{0,44}$/D', $sectionKey)) { throw new \InvalidArgumentException('Use a lowercase section key of at most 45 characters.'); }
        if (!preg_match('~^/[a-z0-9][a-z0-9/_-]{0,90}/$~D', $link)) { throw new \InvalidArgumentException('Use a page link such as /integrations/.'); }
        $base = realpath($root);
        if (false === $base || !is_dir($base)) { throw new \RuntimeException('Choose an existing application root directory.'); }

        $actions = [];
        $class = ucfirst($sectionKey);
        $relative = 'Box/' . $class . '/Integrations.php';
        $target = $base . '/' . $relative;
        $actions[] = ['what' => 'class ' . $relative, 'status' => is_file($target) ? 'exists' : ($apply ? 'created' : 'missing')];
        if ($apply && !is_file($target)) {
            $dir = dirname($target);
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) { throw new \RuntimeException('Cannot create the box directory.'); }
            $code = "<?php\nnamespace $namespace\\Box\\$class;\n\n/** Integration keys for the central WBengine Admin. All behavior is in the framework box. */\nclass Integrations extends \\Wbengine\\Box\\Integrations\n{\n}\n";
            if (false === file_put_contents($target, $code, LOCK_EX)) { throw new \RuntimeException('Cannot write the box class.'); }
        }

        $section = Db::fetchAssoc('SELECT section_id FROM cms_sections WHERE `key` = ? LIMIT 1', [$sectionKey]);
        $actions[] = ['what' => 'section ' . $sectionKey, 'status' => $section ? 'exists' : ($apply ? 'created' : 'missing')];
        if ($apply && !$section) {
            Db::insert('cms_sections', ['title' => $class, 'key' => $sectionKey, 'active' => 1]);
            $section = ['section_id' => Db::getInserted()];
        }

        $site = Db::fetchAssoc('SELECT site_id FROM cms_sites WHERE link = ? LIMIT 1', [$link]);
        $actions[] = ['what' => 'page ' . $link, 'status' => $site ? 'exists' : ($apply ? 'created' : 'missing')];
        if ($apply && !$site) {
            $root = Db::fetchAssoc('SELECT MIN(site_id) AS id FROM cms_sites');
            $row = ['parent_id' => (int) ($root['id'] ?? 0), 'left_id' => 1, 'right_id' => 1, 'strict' => 1, 'visible' => 1, 'link' => $link, 'title' => 'Integrations', 'html_title' => 'Integrations', 'html_description' => 'Integration keys for the central WBengine Admin', 'html_keywords' => 'integrations,api'];
            if ($this->hasColumn('cms_sites', 'acl')) { $row['acl'] = 1; }
            Db::insert('cms_sites', $row);
            $site = ['site_id' => Db::getInserted()];
        }

        $box = Db::fetchAssoc('SELECT id FROM cms_boxes WHERE module = ? AND method = ? LIMIT 1', [self::MODULE, self::METHOD]);
        $actions[] = ['what' => 'box ' . self::MODULE . '/' . self::METHOD, 'status' => $box ? 'exists' : ($apply ? 'created' : 'missing')];
        if ($apply && !$box && $site && $section) {
            $row = ['name' => 'Integrations', 'module' => self::MODULE, 'method' => self::METHOD, 'section_id' => (int) $section['section_id'], 'static' => 1, 'shared' => 0, 'device_min' => 1, 'device_strict' => 0, 'order' => 1, 'sites' => json_encode([(string) $site['site_id']])];
            if ($this->hasColumn('cms_boxes', 'acl')) { $row['acl'] = 1; }
            Db::insert('cms_boxes', $row);
        }

        $problems = (new Preflight())->problems($apiEnabled, true, '');
        return ['applied' => $apply, 'actions' => $actions, 'problems' => array_values($problems)];
    }

    private function hasColumn(string $table, string $column): bool
    {
        // Both names are constants of this class, never input.
        return (bool) Db::fetchAssoc('SHOW COLUMNS FROM `' . $table . '` LIKE \'' . $column . '\'');
    }
}
