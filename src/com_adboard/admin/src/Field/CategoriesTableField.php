<?php
namespace Joomla\Component\Adboard\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;

/**
 * Custom form field that renders a dynamic add/remove table for ad categories.
 *
 * Each row holds:
 *   slug        — internal identifier (hidden, auto-generated from title)
 *   title       — default / English display name
 *   title_pl_PL — Polish display name
 *
 * The slug is auto-derived from the English title on typing (with diacritic
 * transliteration) and deduplication. Once set it should not be changed.
 */
class CategoriesTableField extends FormField
{
    protected $type = 'CategoriesTable';

    /** Full-width wrapper — bypasses Joomla's label+input column grid. */
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
        $rows = [];
        foreach ((array) ($this->value ?? []) as $row) {
            $row    = (array) $row;
            $rows[] = [
                'slug'        => $row['slug']        ?? '',
                'title'       => $row['title']       ?? '',
                'title_pl_PL' => $row['title_pl_PL'] ?? '',
            ];
        }

        $name  = $this->name;
        $id    = $this->id;
        $count = count($rows);

        // Load field CSS from adboard.css (CSP-safe — no inline <style>)
        Factory::getDocument()->addStyleSheet(
            Uri::root(true) . '/media/com_adboard/css/adboard.css',
            ['version' => 'auto']
        );

        $out = '';

        $out .= '<div id="' . $id . '_wrap" class="ab-options-table">'
            . '<table>'
            . '<colgroup>'
            .   '<col style="width:46%"><col style="width:46%"><col style="width:8%">'
            . '</colgroup>'
            . '<thead><tr>'
            .   '<th>' . Text::_('COM_ADBOARD_CAT_TITLE_DEFAULT') . '</th>'
            .   '<th>' . Text::_('COM_ADBOARD_CAT_TITLE_PL')      . '</th>'
            .   '<th style="text-align:right">'
            .     '<button type="button" class="btn btn-sm btn-success"'
            .             ' data-field-id="' . $id . '"'
            .             ' data-field-name="' . htmlspecialchars($name, ENT_QUOTES) . '"'
            .             ' onclick="abCatAdd(this)"'
            .             ' title="' . Text::_('JGLOBAL_FIELD_ADD') . '">'
            .       '<span class="icon-plus" aria-hidden="true"></span>'
            .     '</button>'
            .   '</th>'
            . '</tr></thead>'
            . '<tbody id="' . $id . '_tbody">';

        foreach ($rows as $i => $row) {
            $out .= $this->buildRow($name, $i, $row['slug'], $row['title'], $row['title_pl_PL']);
        }

        $out .= '</tbody></table></div>';

        // ── Inline JS ─────────────────────────────────────────────────────
        // Kept inline because this field renders inside com_config which does
        // not load external component scripts. Counter and tbody-id are passed
        // as JSON-encoded literals to avoid any XSS via the field name/id.
        $jsTbId  = json_encode($id . '_tbody');
        $jsCount = $count;

        $out .= '<script>(function(){';

        // Diacritic map for slug generation
        $out .= 'var D={"ą":"a","ę":"e","ó":"o","ś":"s","ź":"z","ż":"z","ć":"c","ł":"l","ń":"n",'
              . '"Ą":"a","Ę":"e","Ó":"o","Ś":"s","Ź":"z","Ż":"z","Ć":"c","Ł":"l","Ń":"n",'
              . '"ä":"ae","ö":"oe","ü":"ue","ß":"ss","Ä":"ae","Ö":"oe","Ü":"ue",'
              . '"à":"a","á":"a","â":"a","ã":"a","å":"a","è":"e","é":"e","ê":"e","ë":"e",'
              . '"ì":"i","í":"i","î":"i","ï":"i","ò":"o","ô":"o","õ":"o","ø":"o",'
              . '"ù":"u","ú":"u","û":"u","ý":"y","ÿ":"y","ñ":"n","ç":"c"};';
        $out .= 'function slugify(s){return s.split("").map(function(c){return D[c]||c;}).join("")'
              .   '.toLowerCase().replace(/[^a-z0-9]+/g,"-").replace(/^-+|-+$/g,"");}';
        $out .= 'function usedSlugs(tbId){var u={};document.querySelectorAll("#"+tbId+" input[data-slug]")'
              .   '.forEach(function(e){if(e.value)u[e.value]=1;});return u;}';
        $out .= 'function uniqueSlug(base,used){if(!base)return "";if(!used[base])return base;'
              .   'for(var n=2;n<999;n++){var c=base+"-"+n;if(!used[c])return c;}'
              .   'return base+"-"+Date.now();}';
        $out .= 'function attachSlugGen(titleInp,slugInp,tbId){'
              .   'function fill(){if(slugInp.value)return;'
              .     'var used=usedSlugs(tbId);slugInp.value=uniqueSlug(slugify(titleInp.value),used);}'
              .   'titleInp.addEventListener("input",fill);titleInp.addEventListener("blur",fill);}';

        // Wire existing rows on DOMContentLoaded
        $out .= 'var tbId=' . $jsTbId . ';';
        $out .= 'var cnt='  . $jsCount . ';';
        $out .= 'document.addEventListener("DOMContentLoaded",function(){'
              .   'document.querySelectorAll("#"+tbId+" tr").forEach(function(tr){'
              .     'var t=tr.querySelector("input[data-title]"),s=tr.querySelector("input[data-slug]");'
              .     'if(t&&s)attachSlugGen(t,s,tbId);'
              .   '});'
              // Hook ensureSlugs into form submit and Joomla's submitbutton
              .   'function ensureSlugs(){'
              .     'var used={};'
              .     'document.querySelectorAll("#"+tbId+" tr").forEach(function(tr){'
              .       'var t=tr.querySelector("input[data-title]"),s=tr.querySelector("input[data-slug]");'
              .       'if(!s)return;if(s.value){used[s.value]=1;return;}'
              .       'if(!t||!t.value)return;'
              .       'var slug=uniqueSlug(slugify(t.value),used);s.value=slug;used[slug]=1;'
              .     '});'
              .   '}'
              .   'var form=document.getElementById("adminForm");'
              .   'if(form)form.addEventListener("submit",ensureSlugs);'
              .   'var origSB=window.Joomla&&window.Joomla.submitbutton;'
              .   'if(origSB){window.Joomla.submitbutton=function(t){ensureSlugs();origSB.apply(this,arguments);};}' 
              . '});';

        // abCatAdd — Add row button handler
        $out .= 'window.abCatAdd=window.abCatAdd||function(btn){'
              .   'var fId=btn.dataset.fieldId,fName=btn.dataset.fieldName;'
              .   'cnt++;'
              .   'var tbody=document.getElementById(fId+"_tbody");'
              .   'var tr=document.createElement("tr");'
              .   'var slugInp=document.createElement("input");'
              .   'slugInp.type="hidden";slugInp.dataset.slug="1";'
              .   'slugInp.name=fName+"["+cnt+"][slug]";'
              .   'var titleInp=document.createElement("input");'
              .   'titleInp.type="text";titleInp.dataset.title="1";'
              .   'titleInp.className="form-control";'
              .   'titleInp.name=fName+"["+cnt+"][title]";'
              .   'var plInp=document.createElement("input");'
              .   'plInp.type="text";plInp.className="form-control";'
              .   'plInp.name=fName+"["+cnt+"][title_pl_PL]";'
              .   'var rmBtn=document.createElement("button");'
              .   'rmBtn.type="button";rmBtn.className="btn btn-sm btn-danger";'
              .   'rmBtn.innerHTML="<span class=\"icon-minus\" aria-hidden=\"true\"></span>";'
              .   'rmBtn.addEventListener("click",function(){tr.remove();});'
              .   'var td1=document.createElement("td");'
              .   'td1.appendChild(slugInp);td1.appendChild(titleInp);'
              .   'var td2=document.createElement("td");td2.appendChild(plInp);'
              .   'var td3=document.createElement("td");td3.style.textAlign="right";td3.appendChild(rmBtn);'
              .   'tr.appendChild(td1);tr.appendChild(td2);tr.appendChild(td3);'
              .   'tbody.appendChild(tr);'
              .   'attachSlugGen(titleInp,slugInp,fId+"_tbody");'
              .   'titleInp.focus();'
              . '};';

        $out .= '}());</script>';

        return $out;
    }

    private function buildRow(
        string $name, int $i,
        string $slug, string $title, string $titlePl
    ): string {
        $s  = htmlspecialchars($slug,    ENT_QUOTES);
        $t  = htmlspecialchars($title,   ENT_QUOTES);
        $tp = htmlspecialchars($titlePl, ENT_QUOTES);
        $n  = htmlspecialchars($name,    ENT_QUOTES);

        return '<tr>'
            . '<td>'
            .   '<input type="hidden" data-slug="1"'
            .          ' name="' . $n . '[' . $i . '][slug]" value="' . $s . '">'
            .   '<input class="form-control" type="text" data-title="1"'
            .          ' name="' . $n . '[' . $i . '][title]" value="' . $t . '">'
            . '</td>'
            . '<td>'
            .   '<input class="form-control" type="text"'
            .          ' name="' . $n . '[' . $i . '][title_pl_PL]" value="' . $tp . '">'
            . '</td>'
            . '<td style="text-align:right">'
            .   '<button type="button" class="btn btn-sm btn-danger"'
            .           ' onclick="this.closest(\'tr\').remove()">'
            .     '<span class="icon-minus" aria-hidden="true"></span>'
            .   '</button>'
            . '</td>'
            . '</tr>';
    }
}
