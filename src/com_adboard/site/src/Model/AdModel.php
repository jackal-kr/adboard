<?php
namespace Joomla\Component\Adboard\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Model for a single published ad on the site front end.
 *
 * Also provides recordView() to increment the hit counter — kept here in the
 * model rather than in the view so the DB write stays in the data layer.
 */
class AdModel extends BaseDatabaseModel
{
    protected function populateState(): void
    {
        $this->setState(
            'ad.id',
            Factory::getApplication()->input->getInt('id', 0)
        );
    }

    /**
     * Load a single active, non-expired ad.
     * Returns null when the ad is not found, unpublished, or expired.
     */
    public function getItem(): ?object
    {
        $id = (int) $this->getState('ad.id');
        if ($id < 1) {
            return null;
        }

        $db  = $this->getDatabase();
        $now = $db->quote(date('Y-m-d H:i:s'));

        return $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName([
                    'id', 'title', 'category', 'description',
                    'contact', 'images', 'created', 'publish_up', 'publish_down',
                ]))
                ->from($db->quoteName('#__adboard'))
                ->where($db->quoteName('id')    . ' = ' . $id)
                ->where($db->quoteName('state') . ' = 1')
                ->where(
                    '(' . $db->quoteName('publish_down') . ' IS NULL OR ' .
                          $db->quoteName('publish_down') . ' >= ' . $now . ')'
                )
        )->loadObject() ?: null;
    }

    /**
     * Increment the hit counter for an ad.
     * Failures are non-fatal — a counter miss is not worth breaking the page.
     */
    public function recordView(int $id): void
    {
        if ($id < 1) {
            return;
        }

        try {
            $db = $this->getDatabase();
            $db->setQuery(
                'UPDATE ' . $db->quoteName('#__adboard') .
                ' SET hits = hits + 1 WHERE id = ' . $id
            )->execute();
        } catch (\Throwable $e) {
            // Non-fatal
        }
    }
}
