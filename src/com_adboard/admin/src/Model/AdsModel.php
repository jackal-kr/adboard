<?php
namespace Joomla\Component\Adboard\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;

/**
 * Paginated, filterable list of all ads for the admin backend.
 *
 * Overrides getItems() to run the lazy expiry flip before the SELECT so the
 * list always reflects the true state of every ad.
 */
class AdsModel extends ListModel
{
    public function __construct(array $config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = ['id', 'title', 'category', 'state', 'created', 'publish_down'];
        }
        parent::__construct($config);
    }

    /**
     * Flip any Published ads whose publish_down has passed to state = 2 (Expired)
     * before returning the list, so the grid always shows the real current state.
     */
    public function getItems(): array
    {
        $this->expireOverdueAds();
        return parent::getItems();
    }

    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query
            ->select($db->quoteName([
                'id', 'title', 'category', 'state',
                'created', 'publish_up', 'publish_down', 'contact', 'hits',
            ]))
            ->from($db->quoteName('#__adboard'));

        $state = $this->getState('filter.state', '');
        if ($state !== '') {
            $query->where($db->quoteName('state') . ' = ' . (int) $state);
        }

        $category = $this->getState('filter.category', '');
        if ($category !== '') {
            $query->where($db->quoteName('category') . ' = ' . $db->quote($category));
        }

        $search = trim((string) $this->getState('filter.search', ''));
        if ($search !== '') {
            $query->where(
                $db->quoteName('title') . ' LIKE ' .
                $db->quote('%' . $db->escape($search, true) . '%')
            );
        }

        $allowed  = ['id', 'title', 'category', 'state', 'created', 'publish_down'];
        $orderCol = $this->getState('list.ordering', 'created');
        $orderDir = $this->getState('list.direction', 'DESC');

        if (!in_array($orderCol, $allowed, true)) {
            $orderCol = 'created';
        }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $query->order($db->quoteName($orderCol) . ' ' . $orderDir);

        return $query;
    }

    protected function populateState($ordering = 'created', $direction = 'desc')
    {
        $this->setState('filter.search',
            $this->getUserStateFromRequest('com_adboard.ads.filter.search',   'filter_search',   ''));
        $this->setState('filter.state',
            $this->getUserStateFromRequest('com_adboard.ads.filter.state',    'filter_state',    ''));
        $this->setState('filter.category',
            $this->getUserStateFromRequest('com_adboard.ads.filter.category', 'filter_category', ''));

        parent::populateState($ordering, $direction);
    }

    /**
     * Count ads currently waiting for moderation (state = 0).
     * Used by the view to show a badge in the toolbar page title.
     */
    public function getPendingCount(): int
    {
        $db = $this->getDatabase();

        return (int) $db->setQuery(
            $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__adboard'))
                ->where($db->quoteName('state') . ' = 0')
        )->loadResult();
    }

    // ── Private ───────────────────────────────────────────────────────────

    /**
     * Lazily transition Published ads to Expired (state = 2) when their
     * publish_down has passed.  Runs on every list load — idempotent and fast
     * because it only touches rows that actually need the flip.
     *
     * Errors are caught and silenced so a DB hiccup never breaks the list.
     */
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
            // Non-fatal — the list still loads even when the flip fails
        }
    }
}
