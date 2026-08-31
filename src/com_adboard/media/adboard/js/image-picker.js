/**
 * AdBoard — Site image picker (public ad submission form).
 *
 * Self-initialising.
 * Reads config from: <div id="ab-image-picker" data-max="5">
 *
 * Drag-and-drop reordering is supported within #ab-previews.
 * The cover image is always the FIRST tile — drag any tile to the
 * first position to make it the listing cover photo.
 */
(function () {
    'use strict';

    var max   = 5;
    var files = [];   // in-memory ordered list mirrors the DataTransfer

    var dragSrc = null;

    // ── Init ──────────────────────────────────────────────────────────────

    function init() {
        var root = document.getElementById('ab-image-picker');
        if (!root) { return; }

        max = parseInt(root.dataset.max, 10) || 5;

        var inp = document.getElementById('ab-images-input');
        if (inp) {
            inp.addEventListener('change', onFileSelected);
        }

        updateUI();
    }

    // ── File selection ────────────────────────────────────────────────────

    function onFileSelected(e) {
        var inputEl = e.target;
        var slots   = max - files.length;
        if (slots < 1) { inputEl.value = ''; return; }

        var picked = Array.from(inputEl.files).slice(0, slots);
        if (picked.length === 0) { inputEl.value = ''; return; }

        files = files.concat(picked);
        inputEl.value = '';

        syncToInput();
        updateUI();
        renderPreviews();
    }

    function removeFile(idx) {
        if (idx < 0 || idx >= files.length) { return; }
        files.splice(idx, 1);
        syncToInput();
        updateUI();
        renderPreviews();
    }

    // ── Drag-and-drop reordering ──────────────────────────────────────────

    function addDragHandlers(tile, idx) {
        tile.setAttribute('draggable', 'true');
        tile.style.cursor = 'grab';

        tile.addEventListener('dragstart', function (e) {
            dragSrc = tile;
            e.dataTransfer.effectAllowed = 'move';
            tile.classList.add('ab-dragging');
        });

        tile.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (tile !== dragSrc) { tile.classList.add('ab-drag-over'); }
        });

        tile.addEventListener('dragleave', function () {
            tile.classList.remove('ab-drag-over');
        });

        tile.addEventListener('drop', function (e) {
            e.preventDefault();
            tile.classList.remove('ab-drag-over');
            if (!dragSrc || dragSrc === tile) { return; }

            // Determine original indices from the current DOM order
            var box   = document.getElementById('ab-previews');
            var tiles = Array.from(box.children);
            var srcI  = tiles.indexOf(dragSrc);
            var dstI  = tiles.indexOf(tile);

            // Reorder the files array to match the new visual order
            var moved = files.splice(srcI, 1)[0];
            files.splice(dstI, 0, moved);

            syncToInput();
            renderPreviews();   // re-render with updated indices and cover badge
        });

        tile.addEventListener('dragend', function () {
            tile.classList.remove('ab-dragging');
            document.querySelectorAll('.ab-drag-over').forEach(function (el) {
                el.classList.remove('ab-drag-over');
            });
            dragSrc = null;
        });
    }

    // ── Sync / UI ─────────────────────────────────────────────────────────

    function syncToInput() {
        var inp = document.getElementById('ab-images-input');
        if (!inp) { return; }
        var dt = new DataTransfer();
        files.forEach(function (f) { dt.items.add(f); });
        inp.files = dt.files;
    }

    function updateUI() {
        var btn     = document.getElementById('ab-add-btn');
        var counter = document.getElementById('ab-img-count');
        var count   = files.length;
        if (btn)     { btn.style.display = (count >= max) ? 'none' : ''; }
        if (counter) { counter.textContent = count + ' / ' + max; }
    }

    // ── Previews ──────────────────────────────────────────────────────────

    function renderPreviews() {
        var box = document.getElementById('ab-previews');
        if (!box) { return; }

        while (box.firstChild) { box.removeChild(box.firstChild); }

        files.forEach(function (file, idx) {
            var tile = buildTile(file, idx);
            addDragHandlers(tile, idx);
            box.appendChild(tile);
        });
    }

    function buildTile(file, idx) {
        var tile       = document.createElement('div');
        tile.className = 'ab-preview';

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
            rmBtn.addEventListener('click', function () { removeFile(i); });
        }(idx));
        tile.appendChild(rmBtn);

        if (idx === 0) {
            var badge       = document.createElement('span');
            badge.className = 'ab-preview__cover';
            badge.textContent = 'Cover';
            tile.appendChild(badge);
        }

        return tile;
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.AdBoard = window.AdBoard || {};
    window.AdBoard.ImagePicker = { init: init };
}());
