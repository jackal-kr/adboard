<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 Oleksiy Degtyar. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
namespace Joomla\Component\Adboard\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Component\Adboard\Administrator\Helper\TextHelper;

/**
 * Model for a single ad in the admin backend.
 *
 * State rules enforced at save time:
 *   - state = 2 (Expired) cannot be set via the form — it is managed
 *     automatically by the lazy flip.  If received, it is silently mapped
 *     to state = 0 (Pending).
 *   - state = 1 (Published) is rejected when publish_down is in the past —
 *     the moderator must extend the expiry date before re-publishing.
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

    public function getItem(): ?object
    {
        $id = (int) $this->getState('ad.id');

        if ($id === 0) {
            return (object) [
                'id'           => 0,
                'title'        => '',
                'category'     => '',
                'description'  => '',
                'contact'      => '',
                'images'       => null,
                'state'        => 0,
                'created'      => date('Y-m-d H:i:s'),
                'publish_up'   => null,
                'publish_down' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'ip_address'   => '',
            ];
        }

        $db = $this->getDatabase();

        return $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__adboard'))
                ->where($db->quoteName('id') . ' = ' . $id)
        )->loadObject() ?: null;
    }

    /**
     * @return int|false  Saved record ID, or false on validation failure.
     */
    public function save(array $data): int|false
    {
        $db  = $this->getDatabase();
        $now = date('Y-m-d H:i:s');
        $id  = (int) ($data['id'] ?? 0);

        // ── Validate and sanitise ─────────────────────────────────────────

        $title = TextHelper::sanitize($data['title'] ?? '', 255);
        if ($title === '') {
            $this->setError(Text::sprintf(
                'COM_ADBOARD_FIELD_REQUIRED_S', Text::_('COM_ADBOARD_FIELD_TITLE')
            ));
            return false;
        }

        $category = trim((string) ($data['category'] ?? ''));
        if ($category === '') {
            $this->setError(Text::sprintf(
                'COM_ADBOARD_FIELD_REQUIRED_S', Text::_('COM_ADBOARD_FIELD_CATEGORY')
            ));
            return false;
        }

        // state = 2 (Expired) is now a valid selectable state (admin can save as Expired).
        // Auto-conversion: if publish_down is in the past and state is Pending or Published,
        // force state to Expired so the DB always reflects reality.
        $state = (int) ($data['state'] ?? 0);
        if (!in_array($state, [0, 1, 2, -1, -2], true)) {
            $state = 0;
        }

        $publishDown = !empty($data['publish_down'])
            ? date('Y-m-d H:i:s', strtotime((string) $data['publish_down']))
            : null;

        // Auto-convert to Expired when publish_down is in the past.
        // Applies to Pending (0) and Published (1) — Rejected / Trashed / already-Expired
        // are left as-is so the moderator's explicit state choice is respected.
        if ($publishDown !== null && $publishDown < $now && in_array($state, [0, 1], true)) {
            $state = 2;
        }

        $row = (object) [
            'title'        => $title,
            'category'     => $category,
            'description'  => TextHelper::sanitize($data['description'] ?? '', 10000),
            'contact'      => TextHelper::sanitize($data['contact']     ?? '',   255),
            'state'        => $state,
            'publish_down' => $publishDown,
        ];

        try {
            if ($id > 0) {
                $original = $db->setQuery(
                    'SELECT created, publish_up, publish_down FROM ' .
                    $db->quoteName('#__adboard') . ' WHERE id = ' . $id
                )->loadObject();

                $row->id = $id;
                $db->updateObject('#__adboard', $row, 'id');

                if ($state === 1 && $original !== null) {
                    if (empty($original->publish_up)) {
                        // First approval — shift full duration to start from NOW
                        $db->setQuery(
                            'UPDATE ' . $db->quoteName('#__adboard') .
                            ' SET publish_up = ' . $db->quote($now) .
                            ', publish_down = DATE_ADD(' . $db->quote($now) .
                                ', INTERVAL TIMESTAMPDIFF(SECOND,' .
                                $db->quote($original->created) . ',' .
                                $db->quote($original->publish_down) .
                                ') SECOND)' .
                            ' WHERE id = ' . $id
                        )->execute();
                    } else {
                        // Re-publication — refresh publish_up only
                        $db->setQuery(
                            'UPDATE ' . $db->quoteName('#__adboard') .
                            ' SET publish_up = ' . $db->quote($now) .
                            ' WHERE id = ' . $id
                        )->execute();
                    }
                }

                return $id;
            }

            // Insert
            $row->created    = $now;
            $row->ip_address = '0.0.0.0';
            $row->images     = null;

            if ($state === 1) {
                $row->publish_up = $now;
            }

            $db->insertObject('#__adboard', $row, 'id');
            return (int) $row->id;

        } catch (\Throwable $e) {
            $this->setError($e->getMessage());
            return false;
        }
    }

    public function getImages(int $id): array
    {
        $db  = $this->getDatabase();
        $raw = $db->setQuery(
            'SELECT ' . $db->quoteName('images') .
            ' FROM '  . $db->quoteName('#__adboard') .
            ' WHERE ' . $db->quoteName('id') . ' = ' . $id
        )->loadResult();

        return json_decode((string) $raw, true) ?: [];
    }

    public function setImages(int $id, array $images): void
    {
        $db  = $this->getDatabase();
        $row = (object) [
            'id'     => $id,
            'images' => json_encode(array_values($images)),
        ];
        $db->updateObject('#__adboard', $row, 'id');
    }
}
