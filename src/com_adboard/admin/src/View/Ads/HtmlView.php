<?php
namespace Joomla\Component\Adboard\Administrator\View\Ads;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Router\Route;
use Joomla\Component\Adboard\Administrator\Helper\ViewEscapeTrait;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    use ViewEscapeTrait;

    protected $items;
    protected $pagination;
    protected $state;

    /** Permission flags — passed to the template for conditional rendering. */
    public bool $canCreate    = false;
    public bool $canEditState = false;
    public bool $canDelete    = false;
    public bool $canAdmin     = false;

    /** Ads waiting for moderation — shown as badge in the toolbar title. */
    public int $pendingCount = 0;

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
            $this->app->enqueueMessage(
                Text::_('COM_ADBOARD') . ' — ' . $e->getMessage(), 'error'
            );
        }

        $user = Factory::getApplication()->getIdentity();
        $this->canCreate    = $user->authorise('core.create',     'com_adboard');
        $this->canEditState = $user->authorise('core.edit.state', 'com_adboard');
        $this->canDelete    = $user->authorise('core.delete',     'com_adboard');
        $this->canAdmin     = $user->authorise('core.admin',      'com_adboard');
        $this->pendingCount = (int) $this->get('PendingCount');

        $this->addToolbar();
        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        $title = Text::_('COM_ADBOARD_ADS_LIST_TITLE');
        if ($this->pendingCount > 0) {
            $title .= ' <span class="badge bg-warning text-dark ms-2" style="font-size:.7em;vertical-align:middle">'
                    . $this->pendingCount . ' ' . Text::_('COM_ADBOARD_PENDING_BADGE')
                    . '</span>';
        }
        ToolbarHelper::title($title, 'list-2');

        if ($this->canCreate) {
            ToolbarHelper::addNew('ad.add', Text::_('COM_ADBOARD_TOOLBAR_NEW'));
            ToolbarHelper::divider();
        }

        if ($this->canEditState) {
            ToolbarHelper::publishList('ads.publish',     Text::_('COM_ADBOARD_TOOLBAR_PUBLISH'));
            ToolbarHelper::unpublishList('ads.unpublish', Text::_('COM_ADBOARD_TOOLBAR_UNPUBLISH'));
            ToolbarHelper::custom('ads.reject', 'cancel', 'cancel',
                Text::_('COM_ADBOARD_TOOLBAR_REJECT'), true);
            ToolbarHelper::divider();
        }

        if ($this->canDelete) {
            ToolbarHelper::deleteList(Text::_('COM_ADBOARD_CONFIRM_DELETE'), 'ads.delete', Text::_('COM_ADBOARD_TOOLBAR_DELETE'));
            ToolbarHelper::divider();
        }

        if ($this->canAdmin) {
            ToolbarHelper::preferences('com_adboard');
        }

        // Help button — opens the built-in Ad Board usage guide popup
        $helpUrl = Route::_('index.php?option=com_adboard&view=help&tmpl=component', false);
        ToolbarHelper::help('', false, $helpUrl);
    }
}
