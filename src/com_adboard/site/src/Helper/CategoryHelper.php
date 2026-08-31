<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 Oleksiy Degtyar. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
namespace Joomla\Component\Adboard\Site\Helper;

\defined('_JEXEC') or die;

/**
 * Site-namespace alias for the admin CategoryHelper.
 *
 * All logic lives in the Administrator class. This subclass exists solely so
 * site-side code (controllers, models, templates) can import from the local
 * namespace rather than crossing into the Administrator namespace directly.
 */
class CategoryHelper extends \Joomla\Component\Adboard\Administrator\Helper\CategoryHelper
{
}
