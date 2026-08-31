<?php
namespace Joomla\Component\Adboard\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;

/**
 * Provides ad category data from the component's Options parameters.
 *
 * Categories are stored as a JSON array in #__extensions.params under the
 * 'categories' key. Each entry has at minimum: slug, title. Optional per-
 * language overrides follow the naming convention title_{lang_underscored},
 * e.g. title_pl_PL.
 *
 * This class lives in the Administrator namespace but is intentionally
 * accessible from the site namespace too (the PSR-4 autoloader registers
 * both sides of the component). The site CategoryHelper simply extends this
 * class to give templates a clean local-namespace import.
 */
class CategoryHelper
{
    /** @var array<string,object>|null  slug → category object */
    private static ?array $cache = null;

    /**
     * Return all configured categories as a slug-keyed array.
     * Falls back to the two seeded defaults when no categories are saved.
     */
    public static function getAll(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = [];
        $raw         = ComponentHelper::getParams('com_adboard')->get('categories', []);

        foreach ((array) $raw as $cat) {
            $cat = (object) $cat;
            if (isset($cat->slug) && (string) $cat->slug !== '') {
                self::$cache[(string) $cat->slug] = $cat;
            }
        }

        // Fallback to install-time defaults when nothing is configured yet
        if (empty(self::$cache)) {
            self::$cache = [
                'category1' => (object) ['slug' => 'category1', 'title' => 'Category 1', 'title_pl_PL' => 'Kategoria 1'],
                'category2' => (object) ['slug' => 'category2', 'title' => 'Category 2', 'title_pl_PL' => 'Kategoria 2'],
            ];
        }

        return self::$cache;
    }

    /**
     * Return the display title for a slug in the given (or active) language.
     * Falls back to the default title, then to the raw slug.
     */
    public static function getTitle(string $slug, ?string $langTag = null): string
    {
        $langTag  ??= Factory::getApplication()->getLanguage()->getTag();
        $fieldKey   = 'title_' . str_replace('-', '_', $langTag);
        $categories = self::getAll();

        if (isset($categories[$slug])) {
            $cat = $categories[$slug];
            return (!empty($cat->$fieldKey) ? $cat->$fieldKey : null)
                ?? $cat->title
                ?? $slug;
        }

        return $slug; // unknown slug — show it as-is
    }

    /**
     * Return an array of {value, text} option objects suitable for dropdowns.
     */
    public static function getOptions(?string $langTag = null): array
    {
        $options = [];
        foreach (self::getAll() as $slug => $cat) {
            $options[] = (object) ['value' => $slug, 'text' => self::getTitle($slug, $langTag)];
        }
        return $options;
    }

    /** Invalidate the in-process cache (useful after saving component options). */
    public static function flush(): void
    {
        self::$cache = null;
    }
}
