<?php
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

        // Breadcrumb
        $pathway = Factory::getApplication()->getPathway();
        $pathway->addItem(
            Text::_('COM_ADBOARD_ADS_TITLE'),
            Route::_('index.php?option=com_adboard&view=ads')
        );
        $pathway->addItem(Text::_('COM_ADBOARD_FORM_TITLE'));

        parent::display($tpl);
    }
}
