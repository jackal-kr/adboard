<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 Oleksiy Degtyar. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
namespace Joomla\Component\Adboard\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Default dispatcher for the site area.
 * Routes requests with no explicit task to the view specified in the URL
 * (defaults to 'ads').
 */
class DisplayController extends BaseController
{
    protected $default_view = 'ads';
}
