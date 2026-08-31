<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 Oleksiy Degtyar. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
/**
 * Ad Board Smart Search (Finder) plugin — single-file, Joomla 4/5/6 compatible.
 *
 * After installation:
 *  1. System → Plugins → filter Type=finder → enable "Smart Search – Ad Board"
 *  2. Components → Smart Search → Index (toolbar)
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\Finder\Administrator\Indexer\Helper;
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Database\QueryInterface;

// Bail silently if Smart Search is not installed
if (!class_exists('Joomla\\Component\\Finder\\Administrator\\Indexer\\Adapter')) {
    return;
}

class PlgFinderAdboard extends \Joomla\Component\Finder\Administrator\Indexer\Adapter
{
    protected $context    = 'AdBoard';
    protected $extension  = 'com_adboard';
    protected $layout     = 'ad';
    protected $type_title = 'Ad Board';
    protected $table      = '#__adboard';
    protected $autoroute  = false;

    /** Return the database — works with both DI-injected and legacy loading. */
    private function db(): \Joomla\Database\DatabaseInterface
    {
        return $this->db ?? Factory::getDbo();
    }

    /**
     * Look up the first published site menu item pointing to com_adboard.
     * Result is cached statically — only one DB query per indexing run.
     * Returns the menu item ID, or 0 if no menu item exists.
     */
    private function getAdboardItemid(): int
    {
        static $itemid = null;

        if ($itemid !== null) {
            return $itemid;
        }

        try {
            $db = $this->db();
            $q  = $db->createQuery()
                ->select($db->quoteName('m.id'))
                ->from($db->quoteName('#__menu', 'm'))
                ->join(
                    'INNER',
                    $db->quoteName('#__extensions', 'e') .
                    ' ON e.' . $db->quoteName('extension_id') .
                    ' = m.' . $db->quoteName('component_id')
                )
                ->where('e.' . $db->quoteName('element') . ' = ' . $db->quote('com_adboard'))
                ->where('e.' . $db->quoteName('type')    . ' = ' . $db->quote('component'))
                ->where('m.' . $db->quoteName('published')  . ' = 1')
                ->where('m.' . $db->quoteName('client_id')  . ' = 0')
                ->order('m.' . $db->quoteName('id') . ' ASC');

            $db->setQuery($q, 0, 1);
            $itemid = (int) $db->loadResult();
        } catch (\Throwable $e) {
            $itemid = 0;
        }

        return $itemid;
    }

    protected function setup(): bool { return true; }

    // ── Index one ad ──────────────────────────────────────────────────────

    protected function index(Result $item): void
    {
        // url  — stored in #__finder_links.url, used for GC deduplication.
        //        Must NOT include Itemid (GC matching uses LIKE on this value).
        $item->url = $this->getUrl($item->id, $this->extension, $this->layout);

        // route — used by Smart Search to build the result link via Route::_().
        //         Adding &Itemid here ensures the router produces /ogloszenia/1
        //         instead of /component/adboard/1.
        $itemid      = $this->getAdboardItemid();
        $item->route = $item->url . ($itemid ? '&Itemid=' . $itemid : '');

        if (!empty($item->description)) {
            $body          = Helper::parse($item->description);
            $item->summary = $body;
            $item->body    = $body;
        }

        $item->access   = 1;
        $item->language = '*';
        $this->indexer->index($item);
    }

    // ── Query: published, non-expired ads only ────────────────────────────

    protected function getListQuery($query = null)
    {
        $db  = $this->db();
        $now = $db->quote(date('Y-m-d H:i:s'));
        $q   = ($query instanceof QueryInterface) ? $query : $db->createQuery();

        $q->select([
                'a.id', 'a.title', 'a.description', 'a.state',
                'NULL AS catid',
                'a.created      AS start_date',
                'a.publish_up   AS publish_start_date',
                'a.publish_down AS publish_end_date',
            ])
            ->from($db->quoteName('#__adboard', 'a'))
            ->where($db->quoteName('a.state') . ' = 1')
            ->where(
                '(' . $db->quoteName('a.publish_down') . ' IS NULL OR ' .
                      $db->quoteName('a.publish_down') . ' >= ' . $now . ')'
            );

        return $q;
    }

    protected function getItem($id)
    {
        try {
            $db = $this->db();
            return $db->setQuery(
                $this->getListQuery()->where('a.id = ' . (int) $id)
            )->loadObject() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Garbage collection: remove expired/unpublished from index ─────────

    public function onFinderGarbageCollection(): int
    {
        $db      = $this->db();
        $type_id = $this->getTypeId();
        $now     = $db->quote(date('Y-m-d H:i:s'));

        $subquery = $db->createQuery()
            ->select('CONCAT(' . $db->quote(
                $this->getUrl('', $this->extension, $this->layout)
            ) . ', id)')
            ->from($db->quoteName($this->table))
            ->where($db->quoteName('state') . ' = 1')
            ->where(
                '(' . $db->quoteName('publish_down') . ' IS NULL OR ' .
                      $db->quoteName('publish_down') . ' >= ' . $now . ')'
            );

        $query = $db->createQuery()
            ->select($db->quoteName('l.link_id'))
            ->from($db->quoteName('#__finder_links', 'l'))
            ->where($db->quoteName('l.type_id') . ' = ' . $type_id)
            ->where($db->quoteName('l.url') . ' LIKE ' .
                    $db->quote($this->getUrl('%', $this->extension, $this->layout)))
            ->where($db->quoteName('l.url') . ' NOT IN (' . $subquery . ')');

        $db->setQuery($query);
        $items = $db->loadColumn();

        foreach ($items as $item) {
            $this->indexer->remove($item);
        }

        return \count($items);
    }
}
