<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 Oleksiy Degtyar. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Adboard\Site\Helper\CategoryHelper;

// Carry the Itemid so Joomla keeps the correct menu item "active" and
// sidebar modules assigned to the Ogłoszenia menu items keep loading.
$_app   = Factory::getApplication();
$_menu  = $_app->getMenu();
$itemId = (int) $_app->input->getInt('Itemid');
if (!$itemId) {
    foreach ($_menu->getItems('component', 'com_adboard') as $_mi) {
        if (isset($_mi->query['view']) && $_mi->query['view'] === 'ads') {
            $itemId = (int) $_mi->id;
            break;
        }
    }
}
$_iSuffix = $itemId ? '&Itemid=' . $itemId : '';

$activeSearch   = $this->state ? $this->state->get('filter.search',   '') : '';
$activeCategory = $this->state ? $this->state->get('filter.category', '') : '';
$activeSort     = $this->state ? $this->state->get('filter.sort',     'newest') : 'newest';
$listLimit      = $this->state ? (int) $this->state->get('list.limit', 10) : 10;
$mediaBase      = Uri::root() . 'media/com_adboard/ads/';
$filterAction   = Route::_('index.php?option=com_adboard&view=ads' . $_iSuffix);
$hasFilter      = ($activeSearch !== '' || $activeCategory !== '');
$clearUrl       = Route::_('index.php?option=com_adboard&view=ads&filter_search=&filter_category=&limit=10' . $_iSuffix);
$postAdUrl      = Route::_('index.php?option=com_adboard&view=form' . $_iSuffix);
?>

<div class="ab-ads-board">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h2 class="mb-0"><?= Text::_('COM_ADBOARD_ADS_TITLE') ?></h2>
        <a href="<?= $postAdUrl ?>" class="btn btn-primary btn-sm">
            + <?= Text::_('COM_ADBOARD_BTN_POST_AD') ?>
        </a>
    </div>

    <!-- ── Filter bar ─────────────────────────────────────────────────── -->
    <form action="<?= $filterAction ?>" method="get" id="ab-filter-form">
        <input type="hidden" name="option" value="com_adboard">
        <input type="hidden" name="view"   value="ads">

        <!-- ── Row 1: Primary filters (search + category + apply/clear) ── -->
        <div class="row g-2 mb-2 align-items-center">

            <div class="col-sm-4">
                <input type="text"
                       name="filter_search"
                       class="form-control form-control-sm"
                       placeholder="<?= Text::_('COM_ADBOARD_FILTER_SEARCH') ?>"
                       value="<?= $this->escape($activeSearch) ?>">
            </div>

            <div class="col-sm-auto">
                <select name="filter_category"
                        class="form-select form-select-sm"
                        style="min-width:180px">
                    <option value=""><?= Text::_('COM_ADBOARD_CATEGORY_ALL') ?></option>
                    <?php
                    $catMatched = false;
                    foreach ($this->categories as $cat):
                        $isSel = ($activeCategory !== '' && $activeCategory === $cat->value);
                        if ($isSel) $catMatched = true;
                    ?>
                    <option value="<?= $this->escape($cat->value) ?>"
                        <?= $isSel ? 'selected' : '' ?>>
                        <?= $this->escape($cat->text) ?>
                    </option>
                    <?php endforeach; ?>
                    <?php // Show a legacy slug that no longer exists in config
                    if ($activeCategory !== '' && !$catMatched): ?>
                    <option value="<?= $this->escape($activeCategory) ?>" selected>
                        <?= $this->escape(CategoryHelper::getTitle($activeCategory)) ?>
                    </option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="col-sm-auto">
                <button type="submit" class="btn btn-primary btn-sm">
                    <?= Text::_('COM_ADBOARD_BTN_FILTER') ?>
                </button>
                <?php if ($hasFilter): ?>
                <a href="<?= $clearUrl ?>" class="btn ab-btn-clear btn-sm">
                    <?= Text::_('COM_ADBOARD_BTN_CLEAR') ?>
                </a>
                <?php else: ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                    <?= Text::_('COM_ADBOARD_BTN_CLEAR') ?>
                </button>
                <?php endif; ?>
            </div>

        </div>

        <!-- ── Row 2: Secondary controls (sort + per-page), auto-submit on change ── -->
        <div class="d-flex align-items-center gap-3 border-bottom pb-2 mb-4">

            <span class="text-muted small"><?= Text::_('COM_ADBOARD_SORT_LABEL') ?>:</span>
            <select name="filter_sort"
                    id="ab-sort-select"
                    class="form-select form-select-sm ab-select-compact">
                <option value="newest"   <?= $activeSort === 'newest'   ? 'selected' : '' ?>>
                    <?= Text::_('COM_ADBOARD_SORT_NEWEST') ?>
                </option>
                <option value="expiring" <?= $activeSort === 'expiring' ? 'selected' : '' ?>>
                    <?= Text::_('COM_ADBOARD_SORT_EXPIRING') ?>
                </option>
                <option value="oldest"   <?= $activeSort === 'oldest'   ? 'selected' : '' ?>>
                    <?= Text::_('COM_ADBOARD_SORT_OLDEST') ?>
                </option>
            </select>

            <span class="text-muted small ms-auto"><?= Text::_('COM_ADBOARD_PERPAGE_LABEL') ?>:</span>
            <select name="limit"
                    id="ab-limit-select"
                    class="form-select form-select-sm ab-select-compact">
                <?php foreach ([5, 10, 15, 20] as $lim): ?>
                <option value="<?= $lim ?>"
                    <?= $listLimit === $lim ? 'selected' : '' ?>>
                    <?= $lim ?>
                </option>
                <?php endforeach; ?>
            </select>

        </div>
    </form>

    <?php if (empty($this->items)): ?>
        <p class="text-muted"><?= Text::_('COM_ADBOARD_NO_ITEMS') ?></p>
    <?php else: ?>

    <div class="row row-cols-1 row-cols-md-2 g-4 mb-4">
        <?php foreach ($this->items as $item):
            $catTitle  = CategoryHelper::getTitle($item->category);
            $detailUrl = Route::_('index.php?option=com_adboard&view=ad&id=' . (int) $item->id . $_iSuffix);
            $excerpt   = mb_strimwidth(strip_tags($item->description ?? ''), 0, 200, '…');
            $firstImg  = null;
            if (!empty($item->images)) {
                $imgs     = json_decode($item->images, true) ?? [];
                $firstImg = $imgs[0] ?? null;
            }
        ?>
        <div class="col">
            <div class="card h-100 shadow-sm">
                <?php if ($firstImg): ?>
                <img src="<?= $mediaBase . $this->escape($firstImg) ?>"
                     alt="<?= $this->escape($item->title) ?>"
                     class="card-img-top"
                     style="height:180px;object-fit:cover">
                <?php endif; ?>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">
                            <a href="<?= $detailUrl ?>"
                               class="text-decoration-none stretched-link">
                                <?= $this->escape($item->title) ?>
                            </a>
                        </h5>
                        <span class="badge bg-secondary ms-2 text-nowrap">
                            <?= $this->escape($catTitle) ?>
                        </span>
                    </div>
                    <?php if ($excerpt): ?>
                    <p class="card-text text-muted small"><?= $this->escape($excerpt) ?></p>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent text-muted small
                            d-flex justify-content-between">
                    <span>
                        <?= $item->publish_up
                            ? HTMLHelper::_('date', $item->publish_up,  'd.m.Y H:i')
                            : HTMLHelper::_('date', $item->created,     'd.m.Y') ?>
                    </span>
                    <?php if ($item->publish_down): ?>
                    <span>
                        <?= Text::_('COM_ADBOARD_EXPIRES') ?>:
                        <?= HTMLHelper::_('date', $item->publish_down, 'd.m.Y H:i') ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>


    <?php if ($this->pagination): ?>
    <?php
        $pgLimit   = max(1, (int) $this->pagination->limit);
        $pgOffset  = (int) $this->pagination->limitstart;
        $pgTotal   = (int) $this->pagination->total;
        $pgCurrent = (int) floor($pgOffset / $pgLimit) + 1;
        $pgPages   = max(1, (int) ceil($pgTotal / $pgLimit));
    ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
        <?php if ($pgPages > 1): ?>
        <div><?= $this->pagination->getPagesLinks() ?></div>
        <?php else: ?>
        <div></div><!-- spacer so counter stays right-aligned -->
        <?php endif; ?>
        <div class="text-muted small">
            <?= Text::sprintf('COM_ADBOARD_PAGE_X_OF_Y', $pgCurrent, $pgPages) ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

</div>
