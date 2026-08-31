<?php
/**
 * @package     Adboard
 * @copyright   Copyright (C) 2026 JOD. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
namespace Joomla\Component\Adboard\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Plain-text sanitisation helper.
 *
 * Used by both the admin AdModel and the site FormModel to ensure all
 * user-supplied text fields are clean before persistence.
 *
 * Strategy (in order):
 *   1. Strip null bytes and non-printable ASCII control characters
 *      (preserves tab, LF, CR which are legitimate in textarea fields).
 *   2. Decode HTML entities, then strip_tags — prevents double-encoded
 *      injection such as &lt;script&gt; surviving a single pass.
 *   3. strip_tags a second time (belt-and-braces against edge cases).
 *   4. Trim whitespace.
 *   5. Enforce an optional maximum character length via mb_substr.
 */
class TextHelper
{
    /**
     * Sanitise a plain-text value.
     *
     * @param  string $value      Raw user input.
     * @param  int    $maxLength  0 = no limit.
     * @return string
     */
    public static function sanitize(string $value, int $maxLength = 0): string
    {
        // Remove null bytes and ASCII control chars (except \t \n \r)
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        // Decode entities then strip any resulting HTML tags
        $value = strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        // Second pass — belt-and-braces against edge cases
        $value = strip_tags($value);

        $value = trim($value);

        return $maxLength > 0 ? mb_substr($value, 0, $maxLength) : $value;
    }
}
