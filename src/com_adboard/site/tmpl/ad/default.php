<?php
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

if (!$this->item) {
    echo '<p class="alert alert-warning">' . Text::_('COM_ADBOARD_AD_NOT_FOUND') . '</p>';
    return;
}

$item      = $this->item;
$mediaBase = Uri::root() . 'media/com_adboard/ads/';

// Carry Itemid so "Wróć do listy" returns to the menu-aware URL and
// sidebar modules (like "Nasze Ogłoszenia") continue to show.
$_app    = Factory::getApplication();
$_menu   = $_app->getMenu();
$_itemId = (int) $_app->input->getInt('Itemid');
if (!$_itemId) {
    foreach ($_menu->getItems('component', 'com_adboard') as $_mi) {
        if (isset($_mi->query['view']) && $_mi->query['view'] === 'ads') {
            $_itemId = (int) $_mi->id;
            break;
        }
    }
}
$_iSuffix = $_itemId ? '&Itemid=' . $_itemId : '';
$backUrl  = Route::_('index.php?option=com_adboard&view=ads' . $_iSuffix);

$images = !empty($item->images) ? (json_decode($item->images, true) ?? []) : [];

// Build the full URL list passed to gallery.js via data-images
$imageUrls = array_map(
    static fn(string $f): string => $mediaBase . $f,
    $images
);
?>

<div class="ab-ad-detail mb-4">

    <a href="<?= $backUrl ?>" class="btn btn-sm btn-outline-secondary mb-3">
        ← <?= Text::_('COM_ADBOARD_BTN_BACK') ?>
    </a>

    <!-- Title + category badge -->
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <h2 class="mb-0"><?= $this->escape($item->title) ?></h2>
        <span class="badge bg-secondary fs-6"><?= $this->escape($item->categoryTitle) ?></span>
    </div>

    <!-- ── Image gallery ──────────────────────────────────────────────── -->
    <?php if (!empty($images)): ?>
    <div class="ab-gallery mb-4"
         id="ab-gallery"
         data-images="<?= htmlspecialchars(json_encode($imageUrls), ENT_QUOTES) ?>">

        <!-- Main image -->
        <div class="ab-gallery__main mb-2 text-center rounded overflow-hidden"
             style="max-height:420px;background-color:var(--bs-body-bg,#fff);
                    border:1px solid var(--bs-border-color,#dee2e6)">
            <img id="ab-main-img"
                 src="<?= $mediaBase . $this->escape($images[0]) ?>"
                 alt="<?= $this->escape($item->title) ?>"
                 class="img-fluid"
                 style="max-height:420px;object-fit:contain"
                 <?php if (count($images) > 1): ?>
                 title="<?= Text::_('COM_ADBOARD_GALLERY_CLICK_FULLSCREEN') ?>"
                 style="max-height:420px;object-fit:contain;cursor:zoom-in"
                 <?php endif; ?>>
        </div>

        <!-- Thumbnail strip (only when more than one image) -->
        <?php if (count($images) > 1): ?>
        <div class="d-flex flex-wrap gap-2 justify-content-start">
            <?php foreach ($images as $idx => $img): ?>
            <img src="<?= $mediaBase . $this->escape($img) ?>"
                 alt=""
                 class="ab-thumb rounded <?= $idx === 0 ? 'ab-thumb--active' : '' ?>"
                 style="width:72px;height:72px;object-fit:cover;cursor:pointer;
                        border:2px solid <?= $idx === 0 ? '#0d6efd' : 'transparent' ?>;
                        transition:border-color .15s"
                 data-idx="<?= $idx ?>">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <!-- Description -->
    <?php if (!empty($item->description)): ?>
    <div class="ab-description mb-4" style="white-space:pre-wrap"><?= $this->escape(trim($item->description)) ?></div>
    <?php endif; ?>

    <!-- Metadata table -->
    <table class="table table-sm table-borderless w-auto">
        <?php if (!empty($item->contact)): ?>
        <tr>
            <th class="text-muted pe-3"><?= Text::_('COM_ADBOARD_FIELD_CONTACT') ?></th>
            <td><?= $this->escape($item->contact) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th class="text-muted pe-3"><?= Text::_('COM_ADBOARD_COL_PUBLISHED') ?></th>
            <td>
                <?= $item->publish_up
                    ? HTMLHelper::_('date', $item->publish_up, 'd.m.Y H:i')
                    : '—' ?>
            </td>
        </tr>
        <?php if (!empty($item->publish_down)): ?>
        <tr>
            <th class="text-muted pe-3"><?= Text::_('COM_ADBOARD_EXPIRES') ?></th>
            <td><?= HTMLHelper::_('date', $item->publish_down, 'd.m.Y H:i') ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <a href="<?= $backUrl ?>" class="btn btn-sm btn-outline-secondary mt-3">
        ← <?= Text::_('COM_ADBOARD_BTN_BACK') ?>
    </a>

</div>
