<?php
namespace Wbengine\Console;

use Wbengine\Db;
use Wbengine\Model\ModelAbstract;

/** Read-only schema checks through the existing model/Db tree. */
final class MigrationStatus extends ModelAbstract
{
    public function inspect(array $required, bool $transactional = false): bool
    {
        foreach ($required as $table => $columns) {
            foreach ($columns as $column) {
                if (!Db::fetchOne('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column])) { return false; }
            }
        }
        if ($transactional) {
            foreach (['cms_articles','cms_sites','cms_menu','cms_sections','cms_boxes','cms_site_translations','cms_menu_translations','cms_article_translations','cms_api_changes','cms_api_drafts','cms_api_revisions','cms_api_images'] as $table) {
                if (strtolower((string) Db::fetchOne('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table])) !== 'innodb') { return false; }
            }
        }
        return true;
    }
}
