<?php
namespace Joomla\Component\Adboard\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;

/**
 * Custom form field that renders a dynamic add/remove table for expiry terms.
 *
 * Each row holds:
 *   days        — number of days (integer, 1–365)
 *   label       — English display label shown in the site submission form
 *   label_pl_PL — Polish label override
 *
 * Uses addEventListener throughout (no inline onclick) so it works correctly
 * inside com_config which re-processes event handlers.
 */
class ExpiryDaysTableField extends FormField
{
    protected $type = 'ExpiryDaysTable';

    private const DEFAULTS = [
        ['days' => 7,  'label' => '7 days',  'label_pl_PL' => '7 dni'],
        ['days' => 14, 'label' => '14 days', 'label_pl_PL' => '14 dni'],
        ['days' => 21, 'label' => '21 days', 'label_pl_PL' => '21 dni'],
        ['days' => 30, 'label' => '30 days', 'label_pl_PL' => '30 dni'],
    ];

    public function renderField($options = []): string
    {
        return '<div class="control-group">'
            . '<div style="width:100%;padding:0 15px">'
            . $this->getInput()
            . '</div></div>';
    }

    public function getLabel(): string
    {
        return '';
    }

    public function getInput(): string
    {
        $rows = $this->loadRows();

        $id    = $this->id;
        $name  = $this->name;
        $tbId  = $id . '_tbody';
        $btnId = $id . '_add_btn';
        $count = count($rows);

        // Load field CSS from adboard.css (CSP-safe — no inline <style>)
        Factory::getDocument()->addStyleSheet(
            Uri::root(true) . '/media/com_adboard/css/adboard.css',
            ['version' => 'auto']
        );

        $out = '';

        // Table
        $out .= '<div id="' . $id . '_wrap" class="ab-options-table">'
              . '<table><thead><tr>'
              .   '<th style="width:80px">'  . Text::_('COM_ADBOARD_EXPIRY_COL_DAYS')     . '</th>'
              .   '<th>'                     . Text::_('COM_ADBOARD_EXPIRY_COL_LABEL')    . '</th>'
              .   '<th>'                     . Text::_('COM_ADBOARD_EXPIRY_COL_LABEL_PL') . '</th>'
              .   '<th style="width:44px">'
              .     '<button type="button" id="' . $btnId . '" class="btn btn-success btn-sm">'
              .     '<span class="icon-plus" aria-hidden="true"></span></button>'
              .   '</th>'
              . '</tr></thead>'
              . '<tbody id="' . $tbId . '">';

        foreach ($rows as $i => $row) {
            $out .= $this->buildRow($name, $i, $row);
        }

        $out .= '</tbody></table></div>';

        // JS — uses JSON-encoded strings for all PHP→JS value passing
        $jsName  = json_encode($name);
        $jsTbId  = json_encode($tbId);
        $jsBtnId = json_encode($btnId);

        $out .= '<script>(function(){';
        $out .= 'var cnt='   . $count   . ';';
        $out .= 'var nm='    . $jsName  . ';';
        $out .= 'var tbId='  . $jsTbId  . ';';
        $out .= 'var btnId=' . $jsBtnId . ';';

        // Build a new <tr> for the Add button
        $out .= 'function makeRow(i){'
              .   'var tr=document.createElement("tr");'
              .   'function numCell(){'
              .     'var td=document.createElement("td");'
              .     'var inp=document.createElement("input");'
              .     'inp.type="number";inp.className="form-control";'
              .     'inp.min=1;inp.max=365;inp.name=nm+"["+i+"][days]";'
              .     'td.appendChild(inp);return td;'
              .   '}'
              .   'function txtCell(field){'
              .     'var td=document.createElement("td");'
              .     'var inp=document.createElement("input");'
              .     'inp.type="text";inp.className="form-control";'
              .     'inp.name=nm+"["+i+"]["+field+"]";'
              .     'td.appendChild(inp);return td;'
              .   '}'
              .   'tr.appendChild(numCell());'
              .   'tr.appendChild(txtCell("label"));'
              .   'tr.appendChild(txtCell("label_pl_PL"));'
              .   'var td=document.createElement("td");'
              .   'var btn=document.createElement("button");'
              .   'btn.type="button";btn.className="btn btn-danger btn-sm";'
              .   'btn.innerHTML="<span class=\'icon-minus\' aria-hidden=\'true\'></span>";'
              .   'btn.addEventListener("click",function(){tr.remove();});'
              .   'td.appendChild(btn);tr.appendChild(td);'
              .   'return tr;'
              . '}';

        $out .= 'document.addEventListener("DOMContentLoaded",function(){'
              .   'var addBtn=document.getElementById(btnId);'
              .   'if(addBtn)addBtn.addEventListener("click",function(){'
              .     'document.getElementById(tbId).appendChild(makeRow(cnt++));'
              .   '});'
              .   'document.querySelectorAll("#"+tbId+" .ab-expiry-rm").forEach(function(b){'
              .     'b.addEventListener("click",function(){b.closest("tr").remove();});'
              .   '});'
              . '});';

        $out .= '}());</script>';

        return $out;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function loadRows(): array
    {
        $rows = [];
        foreach ((array) ($this->value ?? []) as $row) {
            $row  = (array) $row;
            $days = (int) ($row['days'] ?? 0);
            if ($days > 0) {
                $rows[] = [
                    'days'        => $days,
                    'label'       => trim($row['label']        ?? ''),
                    'label_pl_PL' => trim($row['label_pl_PL'] ?? ''),
                ];
            }
        }
        return !empty($rows) ? $rows : self::DEFAULTS;
    }

    private function buildRow(string $name, int $i, array $row): string
    {
        $days    = (int) $row['days'];
        $label   = htmlspecialchars($row['label']        ?? '', ENT_QUOTES);
        $labelPl = htmlspecialchars($row['label_pl_PL'] ?? '', ENT_QUOTES);
        $n       = htmlspecialchars($name, ENT_QUOTES);

        return '<tr>'
            . '<td><input type="number" class="form-control"'
            .       ' name="' . $n . '[' . $i . '][days]"'
            .       ' value="' . $days . '" min="1" max="365"></td>'
            . '<td><input type="text" class="form-control"'
            .       ' name="' . $n . '[' . $i . '][label]" value="' . $label . '"></td>'
            . '<td><input type="text" class="form-control"'
            .       ' name="' . $n . '[' . $i . '][label_pl_PL]" value="' . $labelPl . '"></td>'
            . '<td><button type="button" class="btn btn-danger btn-sm ab-expiry-rm">'
            .   '<span class="icon-minus" aria-hidden="true"></span></button></td>'
            . '</tr>';
    }
}
