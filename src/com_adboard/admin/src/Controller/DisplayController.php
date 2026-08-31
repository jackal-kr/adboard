<?php
namespace Joomla\Component\Adboard\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Default dispatcher for the admin area.
 *
 * Guards the entire admin section with core.manage.  All subsequent
 * per-action checks (create / edit / edit.state / delete / admin) are
 * enforced individually in AdsController and AdController.
 */
class DisplayController extends BaseController
{
    protected $default_view = 'ads';

    public function display($cachable = false, $urlparams = []): mixed
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user->authorise('core.manage', 'com_adboard')
            && !$user->authorise('core.admin', 'com_adboard')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return parent::display($cachable, $urlparams);
    }
}
