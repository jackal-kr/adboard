<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 Oleksiy Degtyar. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
/**
 * @package  AdBoard
 *
 * Custom component class that extends MVCComponent with router support.
 * MVCComponent alone does not implement RouterServiceInterface, so without
 * this class the component router (site/src/Service/Router.php) is never
 * invoked and SEF URLs fall back to plain query strings.
 *
 * Pattern identical to Joomla core com_content → ContentComponent.
 */

namespace Joomla\Component\Adboard\Administrator\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\MVCComponent;

class AdboardComponent extends MVCComponent implements RouterServiceInterface
{
    use RouterServiceTrait;
}
