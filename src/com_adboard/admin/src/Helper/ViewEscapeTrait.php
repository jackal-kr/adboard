<?php
namespace Joomla\Component\Adboard\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Overrides Joomla's default HtmlView::escape() which uses ENT_COMPAT.
 *
 * ENT_COMPAT only encodes double quotes, leaving single quotes unencoded.
 * While all current component templates use double-quoted HTML attributes
 * (making ENT_COMPAT safe today), ENT_QUOTES is the correct default for
 * any future template change and is the explicit recommendation of the
 * OWASP XSS prevention cheat sheet.
 *
 * Usage: add `use ViewEscapeTrait;` in every component HtmlView class.
 * The trait method takes precedence over the parent-class method in PHP.
 */
trait ViewEscapeTrait
{
    /**
     * @param  mixed $var  Value to HTML-encode.
     * @return string      Safe for use in both text nodes and HTML attributes.
     */
    public function escape($var): string
    {
        if (is_int($var) || is_float($var)) {
            return (string) $var;
        }

        return htmlspecialchars((string) $var, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
