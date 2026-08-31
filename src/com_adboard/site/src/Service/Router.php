<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 Oleksiy Degtyar. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
/**
 * @package     AdBoard
 * @subpackage  Site
 *
 * SEF URL router for com_adboard.
 *
 * Produces clean URLs for the three public views:
 *
 *   ?view=ads           →  (menu item root, no segment added)
 *   ?view=form          →  dodaj
 *   ?view=ad&id=12      →  12
 *
 * The ID is all we need for the detail view. No slug column required —
 * the ID uniquely identifies the ad, is always available, and keeps
 * the router trivially simple.
 */

namespace Joomla\Component\Adboard\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterBase;

class Router extends RouterBase
{
    /**
     * Build a SEF URL segment array from Joomla's internal query vars.
     * Called by Route::_() when building every link.
     *
     * @param  mixed  &$query  Input query vars array (modified in-place — consumed keys are removed)
     * @return array           URL segments to append after the menu item path
     */
    public function build(&$query): array
    {
        $segments = [];

        if (empty($query['view'])) {
            return $segments;
        }

        switch ($query['view']) {

            case 'ads':
                // Listing is the menu item root — no extra segment needed
                unset($query['view']);
                break;

            case 'form':
                $segments[] = 'dodaj';
                unset($query['view']);
                break;

            case 'ad':
                if (!empty($query['id'])) {
                    $segments[] = (int) $query['id'];
                    unset($query['view'], $query['id']);
                }
                break;
        }

        return $segments;
    }

    /**
     * Parse incoming SEF URL segments back into Joomla query vars.
     * Called by the router when a friendly URL is requested.
     *
     * @param  mixed  &$segments  URL segments array (modified in-place — consumed segments are removed)
     * @return array              Query vars to merge into the request
     */
    public function parse(&$segments): array
    {
        $vars = [];

        if (empty($segments)) {
            return $vars;
        }

        $seg = array_shift($segments);

        if ($seg === 'dodaj') {
            $vars['view'] = 'form';
            return $vars;
        }

        if (is_numeric($seg)) {
            $vars['view'] = 'ad';
            $vars['id']   = (int) $seg;
            return $vars;
        }

        // Unrecognised segment — fall back to listing
        $vars['view'] = 'ads';
        return $vars;
    }
}
