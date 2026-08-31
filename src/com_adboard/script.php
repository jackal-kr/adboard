<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 Oleksiy Degtyar. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Installation / update script for com_adboard.
 *
 * Key responsibility: ensureTable() runs on BOTH install AND update.
 * The <install><sql> block in the manifest only fires on a fresh first install.
 * If the admin reinstalls or updates the package, that SQL is skipped and the
 * table would be missing. ensureTable() uses CREATE TABLE IF NOT EXISTS so it
 * is completely idempotent — safe to run every time.
 */
class Com_AdboardInstallerScript
{
    public function install($parent): void
    {
        $this->ensureTable();
        $this->seedDefaultCategories();
        $this->ensureUploadDirectory();
        $this->setHelpUrl();
        $this->setDefaultPermissions();
        $this->registerActionLogConfig();
    }

    public function update($parent): void
    {
        $this->ensureTable();
        $this->ensureUploadDirectory();
        $this->setHelpUrl();
        $this->setDefaultPermissions();
    }

    public function uninstall($parent): void {}

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Create the #__adboard table if it does not already exist.
     * Idempotent — safe to run on every install or update.
     */
    private function ensureTable(): void
    {
        Factory::getDbo()->setQuery("
            CREATE TABLE IF NOT EXISTS `#__adboard` (
              `id`               INT(11)       NOT NULL AUTO_INCREMENT,
              `title`            VARCHAR(255)  NOT NULL DEFAULT '',
              `category`         VARCHAR(50)   NOT NULL DEFAULT '',
              `description`      MEDIUMTEXT,
              `contact`          VARCHAR(255)  DEFAULT NULL,
              `images`           VARCHAR(2000) DEFAULT NULL
                                               COMMENT 'JSON array of filenames in media/com_adboard/ads/',
              `state`            TINYINT(3)    NOT NULL DEFAULT 0
                                               COMMENT '0=pending 1=published -1=rejected -2=trashed',
              `created`          DATETIME      NOT NULL,
              `publish_up`       DATETIME      DEFAULT NULL
                                               COMMENT 'Set on first admin approval',
              `publish_down`     DATETIME      DEFAULT NULL
                                               COMMENT 'Expiry — derived from expires_days at submission',
              `hits`             INT UNSIGNED  NOT NULL DEFAULT 0,
              `ip_address`       VARCHAR(45)   DEFAULT NULL
                                               COMMENT 'For spam tracking only — never displayed publicly',
              `checked_out`      INT(11)       DEFAULT 0,
              `checked_out_time` DATETIME      DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_state`    (`state`),
              KEY `idx_category` (`category`),
              KEY `idx_created`  (`created`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ")->execute();
    }

    /**
     * Seed two placeholder categories on a fresh install.
     * Skipped when categories have already been configured (upgrade path).
     */
    private function seedDefaultCategories(): void
    {
        $db = Factory::getDbo();

        $existing = json_decode(
            $db->setQuery(
                $db->getQuery(true)
                    ->select('params')
                    ->from('#__extensions')
                    ->where('element = ' . $db->quote('com_adboard'))
                    ->where('type = '    . $db->quote('component'))
            )->loadResult() ?? '{}',
            true
        ) ?? [];

        if (!empty($existing['categories'])) {
            return;
        }

        $existing['categories'] = [
            ['slug' => 'category1', 'title' => 'Category 1', 'title_pl_PL' => 'Kategoria 1'],
            ['slug' => 'category2', 'title' => 'Category 2', 'title_pl_PL' => 'Kategoria 2'],
        ];

        $db->setQuery(
            $db->getQuery(true)
                ->update('#__extensions')
                ->set('params = ' . $db->quote(json_encode($existing)))
                ->where('element = ' . $db->quote('com_adboard'))
                ->where('type = '    . $db->quote('component'))
        )->execute();
    }

    /**
     * Create the image upload directory with a listing-blocker index.html.
     */

    /**
     * Set default ACL permissions so the Manager group can moderate ads
     * without accessing the Options configuration screen.
     *
     * Called on both install and update so that upgrading users get the
     * correct defaults.  If a Super User has already customised the
     * core.manage rules, this method leaves them untouched.
     */
    private function setDefaultPermissions(): void
    {
        $db = Factory::getDbo();

        // Find the component asset (created by Joomla at install time)
        $asset = $db->setQuery(
            $db->getQuery(true)
                ->select(['id', 'rules'])
                ->from($db->quoteName('#__assets'))
                ->where('name = ' . $db->quote('com_adboard'))
        )->loadObject();

        if (!$asset) {
            return;
        }

        // Do not overwrite if an admin has already configured custom rules
        $current = json_decode($asset->rules ?? '{}', true) ?? [];
        if (!empty($current['core.manage'])) {
            return;
        }

        // Resolve Manager group ID by title (avoids hardcoding an integer
        // that may differ between Joomla installations)
        $managerId = (int) $db->setQuery(
            $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__usergroups'))
                ->where('title = ' . $db->quote('Manager'))
        )->loadResult();

        if (!$managerId) {
            return;
        }

        // Managers can see and use every part of the component EXCEPT Options
        // (core.admin stays empty → only Super Users inherit it from global)
        $rules = json_encode([
            'core.admin'      => [],
            'core.manage'     => [$managerId => 1],
            'core.create'     => [$managerId => 1],
            'core.delete'     => [$managerId => 1],
            'core.edit'       => [$managerId => 1],
            'core.edit.state' => [$managerId => 1],
        ]);

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__assets'))
                ->set('rules = ' . $db->quote($rules))
                ->where('id = ' . (int) $asset->id)
        )->execute();
    }

    
    /**
     * Register com_adboard in #__action_log_config so it appears
     * as a filter option in the Action Logs admin view
     * (administrator/index.php?option=com_actionlogs).
     */
    private function registerActionLogConfig(): void
    {
        $db = Factory::getDbo();

        // Skip if com_actionlogs is not installed
        try {
            $db->setQuery("SHOW TABLES LIKE '" . $db->getPrefix() . "action_log_config'")->execute();
            if (!$db->getNumRows()) return;
        } catch (\Throwable $e) { return; }

        // Check if already registered
        $exists = $db->setQuery(
            $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__action_log_config'))
                ->where('type_alias = ' . $db->quote('com_adboard'))
        )->loadResult();

        if ($exists) return;

        $row = (object)[
            'type_title'  => 'COM_ADBOARD',
            'type_alias'  => 'com_adboard',
            'id_holder'   => 'id',
            'title_holder'=> 'title',
            'table_name'  => '#__adboard',
            'text_prefix' => 'COM_ADBOARD_LOG',
        ];

        try {
            $db->insertObject('#__action_log_config', $row);
        } catch (\Throwable $e) {}
    }

        /**
     * Store the help URL in the extension record so com_config uses it
     * instead of the broken Joomla.org proxy URL.
     *
     * Joomla versions differ on which column/key they read:
     *   – params.helpURL        (some Joomla 4 builds)
     *   – manifest_cache.helplink (some Joomla 5/6 builds)
     *
     * We update both to cover all versions.
     */
    private function setHelpUrl(): void
    {
        $db     = Factory::getDbo();
        $helpUrl = 'index.php?option=com_adboard&view=help&tmpl=component';

        // ── 1. Update params ──────────────────────────────────────────────
        $params = json_decode(
            $db->setQuery(
                $db->getQuery(true)
                    ->select('params')
                    ->from('#__extensions')
                    ->where('element = ' . $db->quote('com_adboard'))
                    ->where('type = '    . $db->quote('component'))
            )->loadResult() ?? '{}',
            true
        ) ?? [];

        $params['helpURL']  = $helpUrl;   // Joomla 4 key
        $params['helpLink'] = $helpUrl;   // alternative key

        // ── 2. Update manifest_cache ──────────────────────────────────────
        $cache = json_decode(
            $db->setQuery(
                $db->getQuery(true)
                    ->select('manifest_cache')
                    ->from('#__extensions')
                    ->where('element = ' . $db->quote('com_adboard'))
                    ->where('type = '    . $db->quote('component'))
            )->loadResult() ?? '{}',
            true
        ) ?? [];

        $cache['helplink'] = $helpUrl;   // stored by installer from <helplink>

        $db->setQuery(
            $db->getQuery(true)
                ->update('#__extensions')
                ->set('params = '         . $db->quote(json_encode($params)))
                ->set('manifest_cache = ' . $db->quote(json_encode($cache)))
                ->where('element = ' . $db->quote('com_adboard'))
                ->where('type = '    . $db->quote('component'))
        )->execute();
    }

    private function ensureUploadDirectory(): void
    {
        $dir = JPATH_ROOT . '/media/com_adboard/ads';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $index = $dir . '/index.html';
        if (!file_exists($index)) {
            @file_put_contents($index, '<!DOCTYPE html><title>.</title>');
        }
    }
}
