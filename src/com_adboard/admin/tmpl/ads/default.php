<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 Oleksiy Degtyar. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
\defined('_JEXEC') or die;

// Resolve the primary site menu item for com_adboard so admin preview links
// produce /ogloszenia/1 instead of /component/adboard/1.
$_adboardItemid = '';
try {
    $__db = \Joomla\CMS\Factory::getDbo();
    $__q  = $__db->getQuery(true)
        ->select($__db->quoteName('m.id'))
        ->from($__db->quoteName('#__menu', 'm'))
        ->join('INNER', $__db->quoteName('#__extensions', 'e')
            . ' ON e.' . $__db->quoteName('extension_id') . ' = m.' . $__db->quoteName('component_id'))
        ->where('e.' . $__db->quoteName('element') . ' = ' . $__db->quote('com_adboard'))
        ->where('e.' . $__db->quoteName('type')    . ' = ' . $__db->quote('component'))
        ->where('m.' . $__db->quoteName('published') . ' = 1')
        ->where('m.' . $__db->quoteName('client_id') . ' = 0')
        ->order('m.' . $__db->quoteName('id') . ' ASC');
    $__db->setQuery($__q, 0, 1);
    $__mid = (int) $__db->loadResult();
    if ($__mid) {
        $_adboardItemid = '&Itemid=' . $__mid;
    }
} catch (\Throwable $e) {}


use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Component\Adboard\Administrator\Helper\CategoryHelper;

$stateMap = [
     0 => ['label' => 'COM_ADBOARD_STATE_PENDING',   'class' => 'bg-warning text-dark'],
     1 => ['label' => 'COM_ADBOARD_STATE_PUBLISHED',  'class' => 'bg-success'],
     2 => ['label' => 'COM_ADBOARD_STATE_EXPIRED',    'class' => 'bg-secondary text-white'],
    -1 => ['label' => 'COM_ADBOARD_STATE_REJECTED',   'class' => 'bg-danger'],
];

$filterSearch   = $this->state ? $this->state->get('filter.search',   '') : '';
$filterState    = $this->state ? $this->state->get('filter.state',    '') : '';
$filterCategory = $this->state ? $this->state->get('filter.category', '') : '';
$currentLimit   = $this->state ? (int) $this->state->get('list.limit', 20) : 20;
$listStart      = $this->state ? (int) $this->state->get('list.start',  0) :  0;

$hasFilter = ($filterSearch !== '' || $filterState !== '' || $filterCategory !== '');
$catOptions = CategoryHelper::getOptions();

?>

<form action="<?= Route::_('index.php?option=com_adboard') ?>"
      method="post" name="adminForm" id="adminForm">

    <!-- Filter bar -->
    <div class="row g-2 mb-3 align-items-center">
        <div class="col-md-4">
            <input type="text" name="filter_search" class="form-control"
                   placeholder="<?= Text::_('COM_ADBOARD_FILTER_SEARCH') ?>"
                   value="<?= $this->escape($filterSearch) ?>">
        </div>

        <div class="col-md-auto">
            <select name="filter_state" class="form-select">
                <option value=""><?= Text::_('COM_ADBOARD_FILTER_ALL_STATES') ?></option>
                <option value="0"  <?= $filterState === '0'  ? 'selected' : '' ?>><?= Text::_('COM_ADBOARD_STATE_PENDING')   ?></option>
                <option value="1"  <?= $filterState === '1'  ? 'selected' : '' ?>><?= Text::_('COM_ADBOARD_STATE_PUBLISHED') ?></option>
                <option value="2"  <?= $filterState === '2'  ? 'selected' : '' ?>><?= Text::_('COM_ADBOARD_STATE_EXPIRED')   ?></option>
                <option value="-1" <?= $filterState === '-1' ? 'selected' : '' ?>><?= Text::_('COM_ADBOARD_STATE_REJECTED')  ?></option>
            </select>
        </div>

        <div class="col-md-auto">
            <select name="filter_category" class="form-select">
                <option value=""><?= Text::_('COM_ADBOARD_FILTER_ALL_CATEGORIES') ?></option>
                <?php foreach ($catOptions as $cat): ?>
                <option value="<?= $this->escape($cat->value) ?>"
                    <?= $filterCategory === $cat->value ? 'selected' : '' ?>>
                    <?= $this->escape($cat->text) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-auto">
            <button type="submit" class="btn btn-primary">
                <?= Text::_('COM_ADBOARD_BTN_FILTER') ?>
            </button>
            <?php if ($hasFilter): ?>
                <a href="<?= Route::_('index.php?option=com_adboard&view=ads&filter_search=&filter_state=&filter_category=') ?>"
                   class="btn btn-secondary">
                    <?= Text::_('COM_ADBOARD_BTN_CLEAR') ?>
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-outline-secondary" disabled>
                    <?= Text::_('COM_ADBOARD_BTN_CLEAR') ?>
                </button>
            <?php endif; ?>
        </div>

        <!-- Per-page selector -->
        <div class="col-md-auto ms-auto">
            <select name="list[limit]" class="form-select form-select-sm"
                    style="width:auto" onchange="this.form.submit()"
                    title="<?= Text::_('JGLOBAL_LIST_LIMIT') ?>">
                <?php foreach ([10, 20, 50, 100] as $lim): ?>
                <option value="<?= $lim ?>" <?= $currentLimit === $lim ? 'selected' : '' ?>>
                    <?= $lim ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Ads table -->
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th style="width:1%">
                    <input type="checkbox" name="checkall-toggle"
                           onclick="Joomla.checkAll(this)" />
                </th>
                <th style="width:3%"><?= Text::_('COM_ADBOARD_COL_ID') ?></th>
                <th><?= Text::_('COM_ADBOARD_COL_TITLE') ?></th>
                <th style="width:11%"><?= Text::_('COM_ADBOARD_COL_CATEGORY') ?></th>
                <th style="width:12%"><?= Text::_('COM_ADBOARD_COL_STATE') ?></th>
                <th style="width:12%"><?= Text::_('COM_ADBOARD_COL_CREATED') ?></th>
                <th style="width:12%"><?= Text::_('COM_ADBOARD_COL_PUBLISHED') ?></th>
                <th style="width:14%;white-space:nowrap"><?= Text::_('COM_ADBOARD_COL_EXPIRES') ?></th>
                <th style="width:5%;text-align:center"
                    title="<?= Text::_('COM_ADBOARD_COL_HITS') ?>">👁</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($this->items)): ?>
            <tr>
                <td colspan="9" class="text-center text-muted py-4">
                    <?= Text::_('COM_ADBOARD_NO_ITEMS') ?>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($this->items as $i => $item):
                $s          = $stateMap[(int) $item->state] ?? ['label' => '', 'class' => 'bg-light'];
                $isExpired  = ((int) $item->state === 2);
            ?>
            <tr <?= $isExpired ? 'style="opacity:.65"' : '' ?>>
                <td><?= HTMLHelper::_('grid.id', $i, $item->id) ?></td>
                <td><?= (int) $item->id ?></td>
                <td>
                    <a href="<?= Route::_('index.php?option=com_adboard&view=ad&id=' . (int) $item->id) ?>">
                        <?= $this->escape($item->title) ?>
                    </a>
                    <?php if ((int) $item->state === 1): ?>
                    <a href="<?= Route::link('site', 'index.php?option=com_adboard&view=ad&id=' . (int) $item->id . $_adboardItemid) ?>"
                       target="_blank" rel="noopener noreferrer"
                       class="ms-1 text-muted"
                       title="<?= Text::_('COM_ADBOARD_VIEW_ON_SITE') ?>"></a>
                    <?php endif; ?>
                    <?php if (!empty($item->contact)): ?>
                        <br><small class="text-muted"><?= $this->escape($item->contact) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= $this->escape(CategoryHelper::getTitle($item->category)) ?></td>
                <td>
                    <span class="badge <?= $s['class'] ?>">
                        <?= $s['label'] ? Text::_($s['label']) : (int) $item->state ?>
                    </span>
                </td>
                <td><?= HTMLHelper::_('date', $item->created, 'd.m.Y H:i') ?></td>
                <td>
                    <?= $item->publish_up
                        ? HTMLHelper::_('date', $item->publish_up, 'd.m.Y H:i')
                        : '<span class="text-muted">—</span>' ?>
                </td>
                <td style="white-space:nowrap">
                    <?php if ($item->publish_down): ?>
                        <?= HTMLHelper::_('date', $item->publish_down, 'd.m.Y H:i') ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-center text-muted"><?= (int) ($item->hits ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>


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

    <input type="hidden" name="task"       value="">
    <input type="hidden" name="limitstart" value="<?= $listStart ?>">
    <input type="hidden" name="boxchecked" value="0">
    <?= HTMLHelper::_('form.token') ?>
</form>
