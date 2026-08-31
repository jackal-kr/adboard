<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 JOD. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
namespace Joomla\Component\Adboard\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Component\Adboard\Administrator\Helper\ImageHelper;

/**
 * Batch actions on the admin ad list.
 *
 * Publish     → core.edit.state  (skips ads with publish_down in the past)
 * Unpublish   → core.edit.state
 * Reject      → core.edit.state
 * Delete      → core.delete
 *
 * There is intentionally NO "Expire" batch action — state = 2 is set
 * automatically by the lazy flip in AdsModel and cannot be set manually.
 */
class AdsController extends BaseController
{
    private string $listUrl = 'index.php?option=com_adboard&view=ads';

    public function publish(): void
    {
        $this->batchState(1, 'COM_ADBOARD_N_ITEMS_PUBLISHED');
    }

    public function unpublish(): void
    {
        $this->batchState(0, 'COM_ADBOARD_N_ITEMS_UNPUBLISHED');
    }

    public function reject(): void
    {
        $this->batchState(-1, 'COM_ADBOARD_N_ITEMS_REJECTED');
    }

    public function delete(): void
    {
        $this->checkToken();

        if (!Factory::getApplication()->getIdentity()->authorise('core.delete', 'com_adboard')) {
            $this->app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_($this->listUrl, false));
            return;
        }

        $ids = $this->checkedIds();
        if (empty($ids)) {
            $this->setRedirect(Route::_($this->listUrl, false));
            return;
        }

        $db = Factory::getDbo();

        $imageJsonRows = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('images'))
                ->from($db->quoteName('#__adboard'))
                ->whereIn($db->quoteName('id'), $ids)
        )->loadColumn();

        foreach ($imageJsonRows as $json) {
            $files = json_decode((string) $json, true) ?: [];
            if (!empty($files)) {
                ImageHelper::deleteFiles($files);
            }
        }

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__adboard'))
                ->whereIn($db->quoteName('id'), $ids)
        )->execute();

        $this->app->enqueueMessage(Text::plural('COM_ADBOARD_N_ITEMS_DELETED', count($ids)));
        $this->writeLog('COM_ADBOARD_LOG_DELETED', ['count' => count($ids), 'ids' => implode(',', $ids)]);
        $this->setRedirect(Route::_($this->listUrl, false));
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Update state for checked IDs.
     *
     * When state = 1 (Publish), first checks each selected ad's publish_down:
     *   - Ads with publish_down < NOW are skipped with a warning.
     *     The moderator must update the expiry date before re-publishing.
     *   - The remaining ads are published normally.
     */
    private function batchState(int $state, string $msgKey): void
    {
        $this->checkToken();

        if (!Factory::getApplication()->getIdentity()->authorise('core.edit.state', 'com_adboard')) {
            $this->app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_($this->listUrl, false));
            return;
        }

        $ids = $this->checkedIds();
        if (empty($ids)) {
            $this->setRedirect(Route::_($this->listUrl, false));
            return;
        }

        // ── Guard: skip publishing ads with expired publish_down ──────────
        if ($state === 1) {
            $db         = Factory::getDbo();
            $expiredIds = array_map('intval', (array) $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__adboard'))
                    ->whereIn($db->quoteName('id'), $ids)
                    ->where($db->quoteName('publish_down') . ' IS NOT NULL')
                    ->where($db->quoteName('publish_down') . ' < NOW()')
            )->loadColumn());

            if (!empty($expiredIds)) {
                $this->app->enqueueMessage(
                    Text::plural('COM_ADBOARD_N_ITEMS_SKIPPED_EXPIRED', count($expiredIds)),
                    'warning'
                );
                $ids = array_values(array_diff($ids, $expiredIds));
            }

            if (empty($ids)) {
                $this->setRedirect(Route::_($this->listUrl, false));
                return;
            }
        }
        // ─────────────────────────────────────────────────────────────────

        $db  = Factory::getDbo();
        $now = $db->quote(date('Y-m-d H:i:s'));

        if ($state === 1) {
            $db->setQuery(
                'UPDATE ' . $db->quoteName('#__adboard') .
                ' SET state = 1' .
                ', publish_up = ' . $now .
                ', publish_down = CASE' .
                    ' WHEN publish_up IS NULL' .
                    ' THEN DATE_ADD(' . $now .
                        ', INTERVAL TIMESTAMPDIFF(SECOND, created, publish_down) SECOND)' .
                    ' ELSE publish_down' .
                ' END' .
                ' WHERE id IN (' . implode(',', $ids) . ')'
            )->execute();
        } else {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__adboard'))
                    ->set($db->quoteName('state') . ' = ' . $state)
                    ->whereIn($db->quoteName('id'), $ids)
            )->execute();
        }

        $this->app->enqueueMessage(Text::plural($msgKey, count($ids)));
        $this->writeLog($msgKey . '_LOG', ['count' => count($ids), 'ids' => implode(',', $ids)]);
        $this->setRedirect(Route::_($this->listUrl, false));
    }

    private function checkedIds(): array
    {
        return array_values(
            array_filter(
                array_map('intval', (array) $this->input->get('cid', [], 'array')),
                static fn(int $id): bool => $id > 0
            )
        );
    }

    /**
     * Write an entry to Joomla's action log (Latest Actions panel).
     * Silently skips if com_actionlogs is unavailable.
     */
    private function writeLog(string $msgKey, array $context = []): void
    {
        try {
            $app  = $this->app;
            $user = $app->getIdentity();

            // Add user info to context (shown in Action Logs UI)
            $context['userid']      = $user->id;
            $context['username']    = $user->username;
            $context['accountlink'] = 'index.php?option=com_users&task=user.edit&id=' . $user->id;

            // Joomla 6.1 API — bootComponent + MVC model
            $model = $app->bootComponent('com_actionlogs')
                ->getMVCFactory()
                ->createModel('Actionlog', 'Administrator', ['ignore_request' => true]);

            $model->addLog([$context], strtoupper($msgKey), 'com_adboard');
        } catch (\Throwable $e) {}
    }
}
