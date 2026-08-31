<?php
namespace Joomla\Component\Adboard\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Component\Adboard\Administrator\Helper\TextHelper;
use Joomla\Component\Adboard\Site\Helper\CategoryHelper;

/**
 * Model for the public ad submission form.
 *
 * Responsibilities:
 *   - IP-based rate limiting (isRateLimited)
 *   - Field validation (validate)
 *   - Record persistence (save)
 *
 * Image handling is done in the controller before save() is called —
 * validated filenames arrive in $data['images'].
 */
class FormModel extends BaseDatabaseModel
{
    /** Fallback expiry options used when none are configured in Options. */
    private const DEFAULT_EXPIRY_DAYS = [7, 14, 21, 30];

    /**
     * Check whether the given IP has exceeded the configured submission rate.
     */
    public function isRateLimited(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }

        $params  = ComponentHelper::getParams('com_adboard');
        $maxAds  = (int) $params->get('rate_limit_max',    3);
        $window  = (int) $params->get('rate_limit_window', 60);

        $db      = $this->getDatabase();
        $cutoff  = date('Y-m-d H:i:s', time() - $window * 60);

        $count = (int) $db->setQuery(
            $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__adboard'))
                ->where($db->quoteName('ip_address') . ' = ' . $db->quote($ip))
                ->where($db->quoteName('created')    . ' > ' . $db->quote($cutoff))
        )->loadResult();

        return $count >= $maxAds;
    }

    /**
     * Validate submitted field values.
     *
     * @param  array $data  Raw POST data (title, category, contact, expires_days).
     * @return string[]     Array of translated error messages (empty = valid).
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Title
        if (empty(trim((string) ($data['title'] ?? '')))) {
            $errors[] = Text::sprintf('COM_ADBOARD_FIELD_REQUIRED_S', Text::_('COM_ADBOARD_FIELD_TITLE'));
        } elseif (mb_strlen($data['title']) > 255) {
            $errors[] = Text::sprintf('COM_ADBOARD_FIELD_TOO_LONG_SS', Text::_('COM_ADBOARD_FIELD_TITLE'), 255);
        }

        // Category — must be in the configured list
        $allowedSlugs = array_keys(CategoryHelper::getAll());
        $category     = (string) ($data['category'] ?? '');
        if ($category === '' || !in_array($category, $allowedSlugs, true)) {
            $errors[] = Text::sprintf('COM_ADBOARD_FIELD_REQUIRED_S', Text::_('COM_ADBOARD_FIELD_CATEGORY'));
        }

        // Contact
        if (empty(trim((string) ($data['contact'] ?? '')))) {
            $errors[] = Text::sprintf('COM_ADBOARD_FIELD_REQUIRED_S', Text::_('COM_ADBOARD_FIELD_CONTACT'));
        } elseif (mb_strlen($data['contact']) > 255) {
            $errors[] = Text::sprintf('COM_ADBOARD_FIELD_TOO_LONG_SS', Text::_('COM_ADBOARD_FIELD_CONTACT'), 255);
        }

        // Expiry — mandatory and must be in the allowed list
        if ((int) ($data['expires_days'] ?? 0) <= 0) {
            $errors[] = Text::sprintf('COM_ADBOARD_FIELD_REQUIRED_S', Text::_('COM_ADBOARD_FIELD_EXPIRES'));
        }

        return $errors;
    }

    /**
     * Persist a validated ad submission.
     *
     * @param  array $data  Validated and sanitised submission data.
     * @return bool
     */
    public function save(array $data): bool
    {
        $db          = $this->getDatabase();
        $now         = date('Y-m-d H:i:s');
        $allowedDays = $this->allowedExpiryDays();

        $expiresDays = (int) ($data['expires_days'] ?? 0);
        if ($expiresDays <= 0 || !in_array($expiresDays, $allowedDays, true)) {
            return false;
        }

        $images = $data['images'] ?? [];

        $row = (object) [
            'title'        => TextHelper::sanitize((string) ($data['title']       ?? ''), 255),
            'category'     => (string) ($data['category']   ?? ''),
            'description'  => TextHelper::sanitize((string) ($data['description'] ?? ''), 10000),
            'contact'      => TextHelper::sanitize((string) ($data['contact']     ?? ''), 255),
            'images'       => !empty($images) ? json_encode(array_values($images)) : null,
            'state'        => 0,
            'created'      => $now,
            'publish_up'   => null,
            // publish_down is set to NOW + expires_days.
            // It will be recalculated on first admin approval so the full duration
            // runs from the approval moment, not the submission moment.
            'publish_down' => date('Y-m-d H:i:s', strtotime("+{$expiresDays} days")),
            'ip_address'   => mb_substr((string) ($data['ip_address'] ?? ''), 0, 45),
        ];

        try {
            $db->insertObject('#__adboard', $row);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Return the list of allowed expiry day values from component Options.
     * Falls back to DEFAULT_EXPIRY_DAYS when nothing is configured.
     *
     * @return int[]
     */
    private function allowedExpiryDays(): array
    {
        $params = ComponentHelper::getParams('com_adboard');
        $rows   = array_values(
            array_filter(
                array_map(
                    static fn($r): int => (int) ((array) $r)['days'],
                    (array) $params->get('expiry_days', [])
                ),
                static fn(int $d): bool => $d > 0
            )
        );

        return !empty($rows) ? $rows : self::DEFAULT_EXPIRY_DAYS;
    }
}
