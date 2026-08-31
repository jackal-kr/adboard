<?php
namespace Joomla\Component\Adboard\Administrator\View\Help;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Component\Adboard\Administrator\Helper\ViewEscapeTrait;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Renders the Ad Board help page inside a Joomla popup (tmpl=component).
 * Opened by the Help toolbar button in the Ads list and Ad edit views.
 */
class HtmlView extends BaseHtmlView
{
    use ViewEscapeTrait;

    /** Configuration summary passed to the template for context-aware tips. */
    public int $maxImages       = 5;
    public int $maxImageSizeMb  = 5;
    public int $rateLimitMax    = 3;
    public int $rateLimitWindow = 60;

    public function display($tpl = null): void
    {
        $params = ComponentHelper::getParams('com_adboard');

        $this->maxImages       = (int) $params->get('max_images',        5);
        $this->maxImageSizeMb  = (int) $params->get('max_image_size',    5);
        $this->rateLimitMax    = (int) $params->get('rate_limit_max',    3);
        $this->rateLimitWindow = (int) $params->get('rate_limit_window', 60);

        parent::display($tpl);
    }
}
