<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 Oleksiy Degtyar. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Adboard\Administrator\Helper\CategoryHelper;

$item      = $this->item;
$isNew     = ($item->id === 0);
$mediaBase = Uri::root() . 'media/com_adboard/ads/';
$maxImages = (int) ComponentHelper::getParams('com_adboard')->get('max_images', 5);

$images = !empty($item->images) ? (json_decode($item->images, true) ?? []) : [];

// Expired (2) is now selectable — admin can explicitly save in Expired state.
// Saving with state 0 or 1 AND a past publish_down auto-converts to Expired in the model.
$stateOptions = [
     0 => 'COM_ADBOARD_STATE_PENDING',
     1 => 'COM_ADBOARD_STATE_PUBLISHED',
     2 => 'COM_ADBOARD_STATE_EXPIRED',
    -1 => 'COM_ADBOARD_STATE_REJECTED',
];

// datetime-local requires 'YYYY-MM-DDTHH:MM' format
$publishDownValue = date('Y-m-d\TH:i', strtotime($item->publish_down ?? '+7 days'));

// Warn when an active ad's expiry has already passed
$now         = date('Y-m-d H:i:s');
$isExpired = !$isNew && (
    (int) $item->state === 2 ||
    ((int) $item->state === 1 && !empty($item->publish_down) && $item->publish_down < $now)
);

$storedCategory = trim((string) ($item->category ?? ''));
?>

<form action="<?= Route::_('index.php?option=com_adboard') ?>"
      method="post" name="adminForm" id="adminForm"
      enctype="multipart/form-data">

    <?php if ($isExpired): ?>
    <div class="alert alert-warning mb-3">
        <strong><?= Text::_('COM_ADBOARD_WARN_EXPIRED_TITLE') ?></strong>
        <?= Text::_('COM_ADBOARD_WARN_EXPIRED_BODY') ?>
    </div>
    <?php endif; ?>

    <div class="row">

        <!-- ── Main column ────────────────────────────────────────────── -->
        <div class="col-md-9">

            <div class="mb-3">
                <label for="jform_title" class="form-label fw-semibold">
                    <?= Text::_('COM_ADBOARD_FIELD_TITLE') ?>
                    <span class="text-danger">*</span>
                </label>
                <input type="text" id="jform_title" name="jform[title]"
                       class="form-control" maxlength="255" required
                       value="<?= $this->escape($item->title) ?>">
            </div>

            <div class="mb-3">
                <label for="jform_description" class="form-label fw-semibold">
                    <?= Text::_('COM_ADBOARD_FIELD_DESCRIPTION') ?>
                </label>
                <textarea id="jform_description" name="jform[description]"
                          class="form-control" rows="10"
                ><?= $this->escape($item->description ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label for="jform_contact" class="form-label fw-semibold">
                    <?= Text::_('COM_ADBOARD_FIELD_CONTACT') ?>
                </label>
                <textarea id="jform_contact" name="jform[contact]"
                          class="form-control" rows="3"
                          maxlength="255"><?= $this->escape($item->contact ?? '') ?></textarea>
            </div>

            <!-- ── Image management ─────────────────────────────────── -->
            <div class="mb-3">
                <label class="form-label fw-semibold">
                    <?= Text::_('COM_ADBOARD_FIELD_IMAGES') ?>
                </label>

                <!--
                    Thumb strip: server-rendered existing images + JS-appended new previews.
                    The id="ab-admin-picker" carries data-max for the JS.
                    Always rendered (even when empty) so JS can append new previews.
                -->
                <div id="ab-admin-picker" data-max="<?= $maxImages ?>">
                    <div id="ab-existing-thumbs"
                         class="d-flex flex-wrap gap-2 mb-2"
                         style="min-height:90px">
                        <?php foreach ($images as $filename): ?>
                        <div class="ab-exist-thumb position-relative"
                             style="width:80px;height:80px;flex-shrink:0"
                             data-file="<?= $this->escape($filename) ?>">
                            <img src="<?= $mediaBase . $this->escape($filename) ?>"
                                 alt=""
                                 class="rounded"
                                 style="width:80px;height:80px;object-fit:cover;border:1px solid #dee2e6">
                            <button type="button"
                                    class="ab-exist-remove"
                                    aria-label="<?= Text::_('COM_ADBOARD_IMG_REMOVE') ?>"
                                    style="position:absolute;top:2px;right:2px;width:22px;height:22px;
                                           border:none;border-radius:50%;background:rgba(200,30,30,.85);
                                           color:#fff;font-size:14px;line-height:1;cursor:pointer;
                                           display:flex;align-items:center;justify-content:center">×</button>
                            <input type="hidden" class="ab-keep-input"
                                   name="keep_images[]"
                                   value="<?= $this->escape($filename) ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <label class="btn btn-outline-secondary btn-sm mb-0" id="ab-add-btn">
                            + <?= Text::_('COM_ADBOARD_BTN_ADD_PHOTOS') ?>
                            <input type="file" id="ab-new-input" name="new_images[]"
                                   multiple accept="image/jpeg,image/png,image/webp,image/gif"
                                   style="display:none"
                                   >
                        </label>
                        <span id="ab-count" class="text-muted small"></span>
                    </div>
                </div>
            </div>

        </div><!-- /col-md-9 -->

        <!-- ── Sidebar ────────────────────────────────────────────────── -->
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-header fw-semibold">
                    <?= Text::_('COM_ADBOARD_SIDEBAR_DETAILS') ?>
                </div>
                <div class="card-body">

                    <!-- Status -->
                    <div class="mb-3">
                        <label for="jform_state" class="form-label">
                            <?= Text::_('COM_ADBOARD_COL_STATE') ?>
                        </label>
                        <select id="jform_state" name="jform[state]" class="form-select">
                            <?php foreach ($stateOptions as $val => $langKey): ?>
                            <option value="<?= $val ?>"
                                <?= (int) $item->state === $val ? 'selected' : '' ?>>
                                <?= Text::_($langKey) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Category -->
                    <div class="mb-3">
                        <label for="jform_category" class="form-label">
                            <?= Text::_('COM_ADBOARD_FIELD_CATEGORY') ?>
                            <span class="text-danger">*</span>
                        </label>
                        <select id="jform_category" name="jform[category]"
                                class="form-select" required>
                            <option value="">
                                <?= Text::_('COM_ADBOARD_FIELD_CATEGORY_CHOOSE') ?>
                            </option>
                            <?php
                            $knownSlugs = array_keys($this->categories);
                            foreach ($this->categories as $slug => $cat):
                                $slugStr = trim((string) $slug);
                                $isSel   = ($storedCategory !== '' && $storedCategory === $slugStr);
                            ?>
                            <option value="<?= $this->escape($slugStr) ?>"
                                    <?= $isSel ? 'selected' : '' ?>>
                                <?= $this->escape(CategoryHelper::getTitle($slugStr)) ?>
                            </option>
                            <?php endforeach; ?>
                            <?php
                            // Show stored value even if it's a legacy slug no longer in config
                            if ($storedCategory !== '' && !in_array($storedCategory, $knownSlugs, true)):
                            ?>
                            <option value="<?= $this->escape($storedCategory) ?>" selected>
                                <?= $this->escape(CategoryHelper::getTitle($storedCategory)) ?>
                            </option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Expiry date/time -->
                    <div class="mb-3">
                        <label for="jform_publish_down" class="form-label">
                            <?= Text::_('COM_ADBOARD_COL_EXPIRES') ?>
                        </label>
                        <?php
                        // HTMLHelper::_('calendar') renders the joomla-field-calendar web component.
                        // Flip data-show-time so the time picker is visible.
                        $calHtml = HTMLHelper::_(
                            'calendar',
                            $publishDownValue,
                            'jform[publish_down]',
                            'jform_publish_down',
                            '%Y-%m-%d %H:%M:%S',
                            ['class' => 'form-control']
                        );
                        $calHtml = str_replace('data-show-time="0"', 'data-show-time="1"', $calHtml);
                        $calHtml = str_replace('data-time-24="0"',   'data-time-24="1"',  $calHtml);
                        echo $calHtml;
                        ?>
                        <?php if ($isExpired): ?>
                        <div class="form-text text-danger">
                            <?= Text::_('COM_ADBOARD_WARN_EXPIRED_HINT') ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!$isNew): ?>
                    <hr>
                    <dl class="row small text-muted mb-0">
                        <dt class="col-5"><?= Text::_('COM_ADBOARD_COL_CREATED') ?></dt>
                        <dd class="col-7">
                            <?= $item->created
                                ? date('d.m.Y H:i', strtotime($item->created))
                                : '—' ?>
                        </dd>
                        <dt class="col-5"><?= Text::_('COM_ADBOARD_FIELD_IP') ?></dt>
                    <dd class="col-7">
                        <?php
                        // '0.0.0.0' is written by AdModel when an admin creates
                        // the ad from the backend — display as "local" in that case.
                        echo (empty($item->ip_address) || $item->ip_address === '0.0.0.0')
                            ? Text::_('COM_ADBOARD_IP_LOCAL')
                            : $this->escape($item->ip_address);
                        ?>
                    </dd>
                    <dt class="col-5"><?= Text::_('COM_ADBOARD_FIELD_HITS') ?></dt>
                    <dd class="col-7"><?= (int) ($item->hits ?? 0) ?></dd>
                    </dl>
                    <?php endif; ?>

                </div>
            </div>
        </div><!-- /col-md-3 -->

    </div><!-- /row -->

    <input type="hidden" name="jform[id]" value="<?= (int) $item->id ?>">
    <input type="hidden" name="task"      value="">
    <?= HTMLHelper::_('form.token') ?>
</form>
