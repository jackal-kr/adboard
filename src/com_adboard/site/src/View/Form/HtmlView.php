<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 Oleksiy Degtyar. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
namespace Joomla\Component\Adboard\Site\View\Form;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Component\Adboard\Administrator\Helper\ViewEscapeTrait;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class HtmlView extends BaseHtmlView
{
    use ViewEscapeTrait;

    public function display($tpl = null): void
    {
        $this->document->setTitle(Text::_('COM_ADBOARD_FORM_TITLE'));

        // Load component CSS and image picker JS using absolute paths —
        // avoids WebAssetManager URI resolution differences in Joomla 6.
        $base = rtrim(Uri::root(true), '/') . '/';
        $doc  = $this->document;
        $doc->addStyleSheet($base . 'media/com_adboard/css/adboard.css',   ['version' => 'auto']);
        $doc->addScript(    $base . 'media/com_adboard/js/image-picker.js', ['version' => 'auto']);

        // Breadcrumb — only add the "Advertisements" crumb if the active
        // menu item isn't already that page (Joomla's own menu-derived
        // breadcrumb covers that case). The form title crumb is always
        // added, since no menu item represents this specific sub-page.
        $app  = Factory::getApplication();
        $menu = $app->getMenu()->getActive();
        $isActiveMenuItem = $menu
            && ($menu->query['option'] ?? null) === 'com_adboard'
            && ($menu->query['view'] ?? null) === 'ads';

        $pathway = $app->getPathway();

        if (!$isActiveMenuItem) {
            $pathway->addItem(
                Text::_('COM_ADBOARD_ADS_TITLE'),
                Route::_('index.php?option=com_adboard&view=ads')
            );
        }

        $pathway->addItem(Text::_('COM_ADBOARD_FORM_TITLE'));

        parent::display($tpl);
    }
}
