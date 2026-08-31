<?php
namespace Joomla\Component\Adboard\Site\View\Ad;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Component\Adboard\Administrator\Helper\ViewEscapeTrait;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Adboard\Site\Helper\CategoryHelper;

class HtmlView extends BaseHtmlView
{
    use ViewEscapeTrait;

    protected $item;

    public function display($tpl = null): void
    {
        /** @var \Joomla\Component\Adboard\Site\Model\AdModel $model */
        $model      = $this->getModel();
        $this->item = $model->getItem();

        if (!$this->item) {
            Factory::getApplication()->redirect(
                Route::_('index.php?option=com_adboard&view=ads'),
                Text::_('COM_ADBOARD_AD_NOT_FOUND'),
                'warning'
            );
            return;
        }

        $model->recordView($this->item->id);

        $this->item->categoryTitle = CategoryHelper::getTitle($this->item->category);

        $this->document->setTitle(
            Text::_('COM_ADBOARD') . ' — ' . $this->escape($this->item->title)
        );

        $base   = rtrim(Uri::root(true), '/') . '/';
        $doc    = $this->document;
        $images = !empty($this->item->images)
            ? (json_decode($this->item->images, true) ?? [])
            : [];

        // Always load CSS (thumbnail strip styles used even for single image)
        $doc->addStyleSheet($base . 'media/com_adboard/css/adboard.css', ['version' => 'auto']);

        // Gallery JS only needed when there are multiple images
        if (count($images) > 1) {
            $doc->addScript($base . 'media/com_adboard/js/gallery.js', ['version' => 'auto']);
        }

        // Breadcrumb — only add the "Advertisements" crumb if the active
        // menu item isn't already that page (Joomla's own menu-derived
        // breadcrumb covers that case). The ad title crumb is always added,
        // since no menu item represents this specific sub-page.
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

        $pathway->addItem(
            $this->escape($this->item->title),
            Route::_('index.php?option=com_adboard&view=ad&id=' . (int) $this->item->id)
        );

        parent::display($tpl);
    }
}
