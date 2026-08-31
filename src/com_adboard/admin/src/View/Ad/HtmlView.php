<?php
namespace Joomla\Component\Adboard\Administrator\View\Ad;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Component\Adboard\Administrator\Helper\ViewEscapeTrait;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Adboard\Administrator\Helper\CategoryHelper;

class HtmlView extends BaseHtmlView
{
    use ViewEscapeTrait;

    protected $item;
    protected $categories;

    public function display($tpl = null): void
    {
        $this->item = $this->get('Item');

        if (!$this->item) {
            Factory::getApplication()->redirect(
                Route::_('index.php?option=com_adboard&view=ads', false)
            );
            return;
        }

        $isNew = ($this->item->id === 0);
        $user  = Factory::getApplication()->getIdentity();

        // Gate: new ads need core.create; existing ads need core.edit.
        $action = $isNew ? 'core.create' : 'core.edit';
        if (!$user->authorise($action, 'com_adboard')) {
            Factory::getApplication()->redirect(
                Route::_('index.php?option=com_adboard&view=ads', false),
                Text::_('JERROR_ALERTNOAUTHOR'),
                'error'
            );
            return;
        }

        $this->categories = CategoryHelper::getAll();

        $base = rtrim(Uri::root(true), '/') . '/';
        $doc  = $this->document;
        $doc->addStyleSheet($base . 'media/com_adboard/css/adboard.css',          ['version' => 'auto']);
        $doc->addScript(    $base . 'media/com_adboard/js/admin-image-picker.js', ['version' => 'auto']);

        $this->addToolbar($isNew);
        parent::display($tpl);
    }

    private function addToolbar(bool $isNew): void
    {
        ToolbarHelper::title(
            $isNew ? Text::_('COM_ADBOARD_NEW_AD') : Text::_('COM_ADBOARD_EDIT_AD'),
            'pencil-2'
        );

        ToolbarHelper::apply('ad.apply');
        ToolbarHelper::save('ad.save');
        ToolbarHelper::divider();
        ToolbarHelper::cancel('ad.cancel', 'JTOOLBAR_CLOSE');
        ToolbarHelper::divider();

        // Help button — opens the built-in Ad Board usage guide popup
        $helpUrl = Route::_('index.php?option=com_adboard&view=help&tmpl=component', false);
        ToolbarHelper::help('', false, $helpUrl);
    }
}
