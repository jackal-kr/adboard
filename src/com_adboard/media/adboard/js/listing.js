/**
 * Ad Board — listing page behaviour.
 *
 * Replaces the inline onchange="this.form.submit()" on the per-page
 * limit selector so the page works under a strict Content-Security-Policy
 * that forbids unsafe-inline event handlers.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form  = document.getElementById('ab-filter-form');
        // Both the per-page limit AND the sort order selectors submit the
        // filter form immediately on change (no need to click Filter).
        ['select[name="limit"]', 'select[name="filter_sort"]'].forEach(function (sel) {
            var el = form && form.querySelector(sel);
            if (el) {
                el.addEventListener('change', function () { form.submit(); });
            }
        });
    });
}());
