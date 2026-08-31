/**
 * AdBoard — Admin image picker (backend ad edit form).
 *
 * Self-initialising.
 * Reads config from: <div id="ab-admin-picker" data-max="5">
 *
 * On submit, PHP receives:
 *   keep_images[]  — filenames of existing images the admin kept,
 *                    IN THE ORDER they appear in the DOM
 *   new_images[]   — new file uploads
 *
 * The FIRST tile in #ab-existing-thumbs is always the cover image.
 * Drag-and-drop reordering updates the hidden-input order so PHP
 * picks up the new cover automatically.
 */
(function () {
    'use strict';

    var max      = 5;
    var newFiles = [];

    // ── Init ──────────────────────────────────────────────────────────────

    function init() {
        var root = document.getElementById('ab-admin-picker');
        if (!root) { return; }

        max = parseInt(root.dataset.max, 10) || 5;

        var inp = document.getElementById('ab-new-input');
        if (inp) {
            inp.addEventListener('change', onNewFilesSelected);
        }

        document.querySelectorAll('.ab-exist-remove').forEach(function (btn) {
            btn.addEventListener('click', function () { removeExisting(this); });
        });

        // Enable drag-and-drop reordering on all existing tiles
        document.querySelectorAll('.ab-exist-thumb').forEach(makeDraggable);

        updateUI();
        updateCoverBadge();
    }

    // ── Drag-and-drop reordering ──────────────────────────────────────────

    var dragSrc = null;   // the tile being dragged

    function makeDraggable(tile) {
        tile.setAttribute('draggable', 'true');
        tile.addEventListener('dragstart', onDragStart);
        tile.addEventListener('dragover',  onDragOver);
        tile.addEventListener('dragleave', onDragLeave);
        tile.addEventListener('drop',      onDrop);
        tile.addEventListener('dragend',   onDragEnd);
        tile.style.cursor = 'grab';
    }

    function onDragStart(e) {
        dragSrc = this;
        e.dataTransfer.effectAllowed = 'move';
        this.classList.add('ab-dragging');
    }

    function onDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (this !== dragSrc) {
            this.classList.add('ab-drag-over');
        }
    }

    function onDragLeave() {
        this.classList.remove('ab-drag-over');
    }

    function onDrop(e) {
        e.preventDefault();
        this.classList.remove('ab-drag-over');
        if (!dragSrc || dragSrc === this) { return; }

        var strip = this.parentNode;
        var nodes = Array.from(strip.children);
        var srcIdx  = nodes.indexOf(dragSrc);
        var destIdx = nodes.indexOf(this);

        // Insert before or after depending on drag direction
        if (srcIdx < destIdx) {
            strip.insertBefore(dragSrc, this.nextSibling);
        } else {
            strip.insertBefore(dragSrc, this);
        }

        updateCoverBadge();
        // No need to sync newFiles order — new-file tiles are appended last
        // and their remove buttons use closure-captured indices.
        // For existing tiles, the DOM order IS the keep_images[] order.
    }

    function onDragEnd() {
        this.classList.remove('ab-dragging');
        document.querySelectorAll('.ab-drag-over').forEach(function (el) {
            el.classList.remove('ab-drag-over');
        });
        dragSrc = null;
    }

    // ── Existing images ───────────────────────────────────────────────────

    function removeExisting(btn) {
        var wrap = btn.closest('.ab-exist-thumb');
        if (!wrap) { return; }
        wrap.dataset.removed = '1';
        wrap.style.display   = 'none';
        var inp = wrap.querySelector('.ab-keep-input');
        if (inp) { inp.disabled = true; }
        updateCoverBadge();
        updateUI();
    }

    // ── New files ─────────────────────────────────────────────────────────

    function onNewFilesSelected(e) {
        var inputEl = e.target;
        var slots   = max - countExisting() - newFiles.length;
        if (slots < 1) { inputEl.value = ''; return; }

        var picked = Array.from(inputEl.files).slice(0, slots);
        if (picked.length === 0) { inputEl.value = ''; return; }

        newFiles = newFiles.concat(picked);
        inputEl.value = '';

        syncNewInput();
        renderNewPreviews();
        updateUI();
    }

    function removeNew(idx) {
        if (idx < 0 || idx >= newFiles.length) { return; }
        newFiles.splice(idx, 1);
        syncNewInput();
        renderNewPreviews();
        updateUI();
    }

    function syncNewInput() {
        var inp = document.getElementById('ab-new-input');
        if (!inp) { return; }
        var dt = new DataTransfer();
        newFiles.forEach(function (f) { dt.items.add(f); });
        inp.files = dt.files;
    }

    // ── UI helpers ────────────────────────────────────────────────────────

    function countExisting() {
        return document.querySelectorAll(
            '#ab-existing-thumbs .ab-exist-thumb:not([data-removed])'
        ).length;
    }

    function updateUI() {
        var total   = countExisting() + newFiles.length;
        var addBtn  = document.getElementById('ab-add-btn');
        var counter = document.getElementById('ab-count');
        if (addBtn)  { addBtn.style.display = (total >= max) ? 'none' : ''; }
        if (counter) { counter.textContent  = total + ' / ' + max; }
    }

    /**
     * Badge the first non-removed tile as "Cover".
     * Called after init, after any removal, and after a drag-drop.
     */
    function updateCoverBadge() {
        document.querySelectorAll('.ab-cover-badge').forEach(function (b) {
            b.remove();
        });
        var first = document.querySelector(
            '#ab-existing-thumbs .ab-exist-thumb:not([data-removed])'
        ) || document.querySelector(
            '#ab-existing-thumbs .ab-new-preview'
        );
        if (!first) { return; }
        var badge       = document.createElement('span');
        badge.className = 'ab-cover-badge';
        badge.textContent = 'Cover';
        first.appendChild(badge);
    }

    // ── New file previews ─────────────────────────────────────────────────

    function renderNewPreviews() {
        var strip = document.getElementById('ab-existing-thumbs');
        if (!strip) { return; }

        strip.querySelectorAll('.ab-new-preview').forEach(function (el) {
            el.remove();
        });

        newFiles.forEach(function (file, idx) {
            var tile       = document.createElement('div');
            tile.className = 'ab-new-preview';

            var reader = new FileReader();
            reader.onload = function (e) {
                var img = document.createElement('img');
                img.src = e.target.result;
                img.alt = '';
                tile.insertBefore(img, tile.firstChild);
            };
            reader.readAsDataURL(file);

            var rmBtn = document.createElement('button');
            rmBtn.type      = 'button';
            rmBtn.className = 'ab-preview__remove';
            rmBtn.setAttribute('aria-label', 'Remove image');
            rmBtn.innerHTML = '&times;';
            (function (i) {
                rmBtn.addEventListener('click', function () { removeNew(i); });
            }(idx));
            tile.appendChild(rmBtn);

            strip.appendChild(tile);
        });

        updateCoverBadge();
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.AdBoard = window.AdBoard || {};
    window.AdBoard.AdminPicker = { removeExisting: removeExisting };
}());
