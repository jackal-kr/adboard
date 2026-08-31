<?php
namespace Joomla\Component\Adboard\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;

/**
 * Public ad listing model.
 *
 * Runs the same lazy expiry flip as the admin model so ads expire
 * correctly even when no admin visits the backend list.
 * Belt-and-braces: the query also filters publish_down >= NOW so an ad
 * is never visible during the tiny window between two page loads.
 */
class AdsModel extends ListModel
{
    public function __construct(array $config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = ['id', 'title', 'category', 'publish_up', 'publish_down'];
        }
        parent::__construct($config);
    }

    public function getItems(): array
    {
        $this->expireOverdueAds();
        return parent::getItems();
    }

    protected function getListQuery()
    {
        $db  = $this->getDatabase();
        $now = $db->quote(date('Y-m-d H:i:s'));

        $query = $db->getQuery(true)
            ->select($db->quoteName([
                'id', 'title', 'category', 'description',
                'contact', 'images', 'created', 'publish_up', 'publish_down',
            ]))
            ->from($db->quoteName('#__adboard'))
            // Only Published (state = 1) — Expired (2), Pending (0), Rejected (-1) excluded
            ->where($db->quoteName('state') . ' = 1')
            // Belt-and-braces: also exclude past publish_down in case the flip hasn't run yet
            ->where(
                '(' . $db->quoteName('publish_down') . ' IS NULL OR ' .
                      $db->quoteName('publish_down') . ' >= ' . $now . ')'
            );

        $search = trim((string) $this->getState('filter.search', ''));
        if ($search !== '') {
            $query->where(
                $db->quoteName('title') . ' LIKE ' .
                $db->quote('%' . $db->escape($search, true) . '%')
            );
        }

        $category = $this->getState('filter.category', '');
        if ($category !== '') {
            $query->where($db->quoteName('category') . ' = ' . $db->quote($category));
        }

        // Sort order — configurable via the filter bar
        $sort     = $this->getState('filter.sort', 'newest');
        $orderSql = match ($sort) {
            'expiring' => $db->quoteName('publish_down') . ' ASC',
            'oldest'   => $db->quoteName('publish_up')   . ' ASC',
            default    => $db->quoteName('publish_up')   . ' DESC',  // newest first
        };
        $query->order($orderSql);

        return $query;
    }

    /**
     * Override the store ID to include filter.sort so Joomla's query cache
     * generates a new cache key whenever the sort order changes.
     * Without this, Joomla reuses the cached query regardless of sort value.
     */
    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.sort', 'newest');
        $id .= ':' . $this->getState('filter.search', '');
        $id .= ':' . $this->getState('filter.category', '');
        return parent::getStoreId($id);
    }

    protected function populateState($ordering = 'publish_up', $direction = 'desc')
    {
        $this->setState('filter.search',
            $this->getUserStateFromRequest(
                'com_adboard.site.ads.filter.search', 'filter_search', ''
            )
        );
        $this->setState('filter.category',
            $this->getUserStateFromRequest(
                'com_adboard.site.ads.filter.category', 'filter_category', ''
            )
        );
        $this->setState('filter.sort',
            $this->getUserStateFromRequest(
                'com_adboard.site.ads.filter.sort', 'filter_sort', 'newest'
            )
        );
        parent::populateState($ordering, $direction);
    }

    private function expireOverdueAds(): void
    {
        try {
            $db = $this->getDatabase();
            $db->setQuery(
                'UPDATE ' . $db->quoteName('#__adboard') .
                ' SET '   . $db->quoteName('state') . ' = 2' .
                ' WHERE ' . $db->quoteName('state') . ' = 1' .
                ' AND '   . $db->quoteName('publish_down') . ' IS NOT NULL' .
                ' AND '   . $db->quoteName('publish_down') . ' < NOW()'
            )->execute();
        } catch (\Throwable $e) {
            // Non-fatal
        }
    }
}
