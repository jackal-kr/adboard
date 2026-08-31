<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 JOD. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
namespace Joomla\Component\Adboard\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Component\Adboard\Administrator\Helper\ImageHelper;

/**
 * Single-ad CRUD in the admin backend.
 *
 * Permission requirements:
 *   save / apply on NEW ad    → core.create
 *   save / apply on EXISTING  → core.edit
 *   add (open blank form)     → core.create
 *   cancel                    → (none)
 *
 * Expiry-date auto-conversion is handled entirely in AdModel::save().
 * Saving with state = Published (1) or Pending (0) while publish_down is in
 * the past automatically stores the ad as Expired (2) — no blocking, no
 * separate controller check needed.
 */
class AdController extends BaseController
{
    private string $listUrl = 'index.php?option=com_adboard&view=ads';

    /** Save and return to list. */
    public function save(): void
    {
        $this->checkToken();

        // Read input BEFORE saveRecord() so we have the original id and title
        $inputData = $this->input->get('jform', [], 'array');
        $isNew     = empty($inputData['id']) || (int) ($inputData['id']) === 0;

        // Snapshot of fields we care about BEFORE save — used to detect real changes
        $snapshotBefore = $isNew ? null : $this->getAdSnapshot((int) $inputData['id']);

        [$savedId, $ok] = $this->saveRecord();

        if ($ok) {
            $this->processImages($savedId);
            $this->app->enqueueMessage(Text::_($isNew ? 'COM_ADBOARD_SAVE_SUCCESS_NEW' : 'COM_ADBOARD_SAVE_SUCCESS'));

            // Only log if something actually changed (or it is a new record)
            $snapshotAfter = $this->getAdSnapshot($savedId);
            if ($isNew || $snapshotBefore !== $snapshotAfter) {
                $this->writeLog(
                    $isNew ? 'COM_ADBOARD_LOG_CREATED' : 'COM_ADBOARD_LOG_UPDATED',
                    ['id' => $savedId, 'title' => $inputData['title'] ?? '']
                );
            }

            $this->setRedirect(Route::_($this->listUrl, false));
        } else {
            $this->showModelErrors($this->currentId());
        }
    }

    /** Save and stay on the edit form. */
    public function apply(): void
    {
        $this->checkToken();

        [$savedId, $ok] = $this->saveRecord();

        if ($ok) {
            $this->processImages($savedId);
            $this->app->enqueueMessage(Text::_('COM_ADBOARD_SAVE_SUCCESS'));
        } else {
            $this->showModelErrors($savedId ?: $this->currentId());
        }

        $this->setRedirect(Route::_(
            'index.php?option=com_adboard&view=ad&id=' . ($savedId ?: $this->currentId()), false
        ));
    }

    /** Discard and return to list. */
    public function cancel(): void
    {
        $this->setRedirect(Route::_($this->listUrl, false));
    }

    /** Open a blank add form — requires core.create. */
    public function add(): void
    {
        if (!Factory::getApplication()->getIdentity()->authorise('core.create', 'com_adboard')) {
            $this->app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_($this->listUrl, false));
            return;
        }

        $this->setRedirect(Route::_('index.php?option=com_adboard&view=ad', false));
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function currentId(): int
    {
        return (int) ($this->input->get('jform', [], 'array')['id'] ?? 0);
    }

    /**
     * Validate permission, then delegate to the model.
     * Returns [saved_id, success].
     */
    private function saveRecord(): array
    {
        $data   = $this->input->get('jform', [], 'array');
        $id     = (int) ($data['id'] ?? 0);
        $user   = Factory::getApplication()->getIdentity();
        $action = ($id > 0) ? 'core.edit' : 'core.create';

        if (!$user->authorise($action, 'com_adboard')) {
            $this->app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            return [$id, false];
        }

        $model   = $this->getModel('Ad', 'Administrator');
        $savedId = $model->save($data);

        if ($savedId === false) {
            return [$id, false];
        }

        return [$savedId, true];
    }

    /**
     * Surface any errors set by the model as amber warnings.
     * Falls back to the generic "could not be saved" message when the model
     * did not record a specific reason.
     */
    private function showModelErrors(int $id): void
    {
        $model  = $this->getModel('Ad', 'Administrator');
        $errors = $model->getErrors();

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->app->enqueueMessage((string) $error, 'warning');
            }
        } else {
            $this->app->enqueueMessage(Text::_('COM_ADBOARD_SAVE_FAILED'), 'error');
        }

        $this->setRedirect(Route::_(
            'index.php?option=com_adboard&view=ad&id=' . $id, false
        ));
    }

    /**
     * Handle image deletions and new uploads for a saved ad.
     */
    private function processImages(int $id): void
    {
        $params    = ComponentHelper::getParams('com_adboard');
        $maxImages = (int) $params->get('max_images', 5);
        $maxSizeMb = (int) $params->get('max_image_size', 5);

        /** @var \Joomla\Component\Adboard\Administrator\Model\AdModel $model */
        $model = $this->getModel('Ad', 'Administrator');

        $keep     = ImageHelper::filterKeepList(
            (array) $this->input->get('keep_images', [], 'array')
        );
        $current  = $model->getImages($id);
        $toDelete = array_diff($current, $keep);

        if (!empty($toDelete)) {
            ImageHelper::deleteFiles($toDelete);
        }

        $slots      = max(0, $maxImages - count($keep));
        $hadRejects = false;
        $newSaved   = ImageHelper::saveUploads('new_images', $slots, $maxSizeMb, $hadRejects);

        $model->setImages($id, array_merge($keep, $newSaved));
    }

    /**
     * Return a hash of the ad's key fields for change detection.
     * Only the fields an admin can edit are included.
     * Returns null if the record does not yet exist (new ad).
     */
    private function getAdSnapshot(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }
        try {
            $db  = \Joomla\CMS\Factory::getDbo();
            $row = $db->setQuery(
                $db->getQuery(true)
                    ->select(['title', 'category', 'description', 'contact', 'state',
                              'publish_up', 'publish_down'])
                    ->from($db->quoteName('#__adboard'))
                    ->where($db->quoteName('id') . ' = ' . $id)
            )->loadAssoc();
            return $row ? md5(serialize($row)) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

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
