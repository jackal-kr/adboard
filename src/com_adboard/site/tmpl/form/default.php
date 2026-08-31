<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 Oleksiy Degtyar. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Captcha\Captcha;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Component\Adboard\Site\Helper\CategoryHelper;

$categories = CategoryHelper::getOptions();
$maxImages  = (int) ComponentHelper::getParams('com_adboard')->get('max_images', 5);
$submitUrl  = Route::_('index.php?option=com_adboard&task=form.submit');

// CAPTCHA
$captchaHtml   = '';
$captchaPlugin = Factory::getApplication()->get('captcha', '0');
if ($captchaPlugin && $captchaPlugin !== '0') {
    try {
        $captchaHtml = Captcha::getInstance($captchaPlugin)->display('captcha', 'captcha', '');
    } catch (\Throwable $e) {
        $captchaHtml = '';
    }
}

// Expiry options from component config, with fallback defaults
$expiryRows = array_values(array_filter(
    array_map(
        static fn($r): array => (array) $r,
        (array) ComponentHelper::getParams('com_adboard')->get('expiry_days', [])
    ),
    static fn(array $r): bool => (int) ($r['days'] ?? 0) > 0
));
if (empty($expiryRows)) {
    $expiryRows = [
        ['days' => 7,  'label' => '7 days',  'label_pl_PL' => '7 dni'],
        ['days' => 14, 'label' => '14 days', 'label_pl_PL' => '14 dni'],
        ['days' => 21, 'label' => '21 days', 'label_pl_PL' => '21 dni'],
        ['days' => 30, 'label' => '30 days', 'label_pl_PL' => '30 dni'],
    ];
}

$langTag   = Factory::getApplication()->getLanguage()->getTag();
$langField = 'label_' . str_replace('-', '_', $langTag);
?>

<div class="ab-form-wrap" style="max-width:680px">

    <h2><?= Text::_('COM_ADBOARD_FORM_TITLE') ?></h2>
    <p class="text-muted"><?= Text::_('COM_ADBOARD_SUBMIT_PENDING_INFO') ?></p>

    <form action="<?= $submitUrl ?>" method="post"
          enctype="multipart/form-data" novalidate>

        <!-- Honeypot — bots fill this, humans leave it empty -->
        <div style="position:absolute;left:-9999px;top:-9999px" aria-hidden="true">
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off">
        </div>

        <!-- Title -->
        <div class="mb-3">
            <label for="ab_title" class="form-label fw-semibold">
                <?= Text::_('COM_ADBOARD_FIELD_TITLE') ?>
                <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <input type="text" id="ab_title" name="title"
                   class="form-control" maxlength="255" required autocomplete="off">
        </div>

        <!-- Category -->
        <div class="mb-3">
            <label for="ab_category" class="form-label fw-semibold">
                <?= Text::_('COM_ADBOARD_FIELD_CATEGORY') ?>
                <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <select id="ab_category" name="category" class="form-select" required>
                <option value=""><?= Text::_('COM_ADBOARD_FIELD_CATEGORY_CHOOSE') ?></option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $this->escape($cat->value) ?>">
                    <?= $this->escape($cat->text) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Description -->
        <div class="mb-3">
            <label for="ab_description" class="form-label fw-semibold">
                <?= Text::_('COM_ADBOARD_FIELD_DESCRIPTION') ?>
            </label>
            <textarea id="ab_description" name="description"
                      class="form-control" rows="7" maxlength="10000"></textarea>
        </div>

        <!-- Contact -->
        <div class="mb-3">
            <label for="ab_contact" class="form-label fw-semibold">
                <?= Text::_('COM_ADBOARD_FIELD_CONTACT') ?>
                <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <textarea id="ab_contact" name="contact"
                      class="form-control" rows="3"
                      maxlength="255" required></textarea>
            <div class="form-text"><?= Text::_('COM_ADBOARD_FIELD_CONTACT_HINT') ?></div>
        </div>

        <!-- Image upload -->
        <div class="mb-3">
            <label class="form-label fw-semibold">
                <?= Text::_('COM_ADBOARD_FIELD_IMAGES') ?>
            </label>

            <!--
                id="ab-image-picker" carries data-max for image-picker.js.
                The JS attaches all listeners on DOMContentLoaded — no inline
                onchange is needed.
            -->
            <div id="ab-image-picker" data-max="<?= $maxImages ?>">
                <div id="ab-previews" class="d-flex flex-wrap gap-2 mb-2"></div>

                <div class="d-flex align-items-center gap-3">
                    <label class="btn btn-outline-secondary btn-sm mb-0" id="ab-add-btn">
                        + <?= Text::_('COM_ADBOARD_BTN_ADD_PHOTOS') ?>
                        <input type="file" id="ab-images-input" name="images[]"
                               multiple accept="image/jpeg,image/png,image/webp,image/gif"
                               style="display:none">
                    </label>
                    <span id="ab-img-count" class="text-muted small">0 / <?= $maxImages ?></span>
                </div>
            </div>
            <div class="form-text"><?= Text::sprintf('COM_ADBOARD_FIELD_IMAGES_HINT', $maxImages) ?></div>
        </div>

        <!-- Expiry duration -->
        <div class="mb-4">
            <label for="ab_expires" class="form-label fw-semibold">
                <?= Text::_('COM_ADBOARD_FIELD_EXPIRES') ?>
                <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <select id="ab_expires" name="expires_days"
                    class="form-select" style="max-width:200px" required>
                <option value="" selected disabled>
                    <?= Text::_('COM_ADBOARD_FIELD_EXPIRES_CHOOSE') ?>
                </option>
                <?php foreach ($expiryRows as $opt):
                    $days  = (int) $opt['days'];
                    $label = !empty($opt[$langField])
                        ? $opt[$langField]
                        : (!empty($opt['label']) ? $opt['label'] : $days . ' days');
                ?>
                <option value="<?= $days ?>">
                    <?= htmlspecialchars($label, ENT_QUOTES) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($captchaHtml !== ''): ?>
        <div class="mb-3"><?= $captchaHtml ?></div>
        <script>
        /* Translate CAPTCHA label from English to Polish.
           Joomla's built-in captcha plugin has no pl-PL language file —
           this targets the label text directly after DOM render. */
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('label, span').forEach(function (el) {
                if (el.childNodes.length === 1 &&
                    el.textContent.trim() === "I'm not a robot") {
                    el.textContent = 'Nie jestem robotem';
                }
            });
        });
        </script>
        <?php endif; ?>

        <p class="text-muted small">
            <span class="text-danger">*</span> <?= Text::_('COM_ADBOARD_FIELD_REQUIRED_NOTE') ?>
        </p>

        <button type="submit" class="btn btn-primary btn-lg">
            <?= Text::_('COM_ADBOARD_BTN_SUBMIT') ?>
        </button>

        <?= HTMLHelper::_('form.token') ?>
    </form>
</div>
