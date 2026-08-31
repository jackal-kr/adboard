<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 Oleksiy Degtyar. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
namespace Joomla\Component\Adboard\Site\View\Ads;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Component\Adboard\Administrator\Helper\ViewEscapeTrait;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Adboard\Site\Helper\CategoryHelper;

class HtmlView extends BaseHtmlView
{
    use ViewEscapeTrait;

    protected $items;
    protected $pagination;
    protected $state;
    protected $categories;

    public function display($tpl = null): void
    {
        try {
            $this->items      = $this->get('Items');
            $this->pagination = $this->get('Pagination');
            $this->state      = $this->get('State');
        } catch (\Throwable $e) {
            $this->items      = [];
            $this->pagination = null;
            $this->state      = new CMSObject();
        }

        $this->categories = CategoryHelper::getOptions();

        // Load component CSS (needed for the filter bar Clear button colour)
        $base = rtrim(Uri::root(true), '/') . '/';
        $this->document->addStyleSheet(
            $base . 'media/com_adboard/css/adboard.css', ['version' => 'auto']
        );
        $this->document->addScript(
            $base . 'media/com_adboard/js/listing.js', ['version' => 'auto'], ['defer' => true]
        );

        $title = Text::_('COM_ADBOARD_ADS_TITLE');
        $this->document->setTitle($title);

        // Only add a breadcrumb for this view if the active menu item isn't
        // already this exact page — otherwise Joomla's own menu-derived
        // breadcrumb already covers it and we'd be duplicating it.
        $app  = Factory::getApplication();
        $menu = $app->getMenu()->getActive();
        $isActiveMenuItem = $menu
            && ($menu->query['option'] ?? null) === 'com_adboard'
            && ($menu->query['view'] ?? null) === 'ads';

        if (!$isActiveMenuItem) {
            $app->getPathway()->addItem($title, Route::_('index.php?option=com_adboard&view=ads'));
        }

        parent::display($tpl);
    }
}
