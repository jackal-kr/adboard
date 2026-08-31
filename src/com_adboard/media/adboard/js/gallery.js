/**
 * AdBoard — Ad detail image gallery + fullscreen lightbox.
 *
 * Gallery:
 *   – Click main image  → open lightbox
 *   – Click thumbnail   → jump to that image (gallery view)
 *   – Arrow keys        → navigate (gallery view)
 *
 * Lightbox:
 *   – Opens on main-image click
 *   – ← / → arrows (on-screen and keyboard) to navigate
 *   – × button (top-right) or Escape key to close
 *   – Click outside the image to close
 *   – Swipe left / right on touch devices
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var gallery = document.getElementById('ab-gallery');
        if (!gallery) return;

        var images  = JSON.parse(gallery.dataset.images || '[]');
        if (!images.length) return;

        var mainImg = document.getElementById('ab-main-img');
        var thumbs  = document.querySelectorAll('.ab-thumb');
        var current = 0;

        /* ── Thumbnail / gallery sync ─────────────────────────────────── */

        function activate(idx) {
            current = ((idx % images.length) + images.length) % images.length;
            if (mainImg) mainImg.src = images[current];
            thumbs.forEach(function (t, i) {
                t.classList.toggle('ab-thumb--active', i === current);
                t.style.borderColor = i === current ? '#0d6efd' : 'transparent';
            });
        }

        thumbs.forEach(function (t, i) {
            t.addEventListener('click', function () { activate(i); });
        });

        document.addEventListener('keydown', function (e) {
            if (lb.style.display === 'flex') return; // lightbox handles keys
            if (e.key === 'ArrowRight') activate(current + 1);
            if (e.key === 'ArrowLeft')  activate(current - 1);
        });

        /* ── Lightbox build ───────────────────────────────────────────── */

        var lb = document.createElement('div');
        lb.id = 'ab-lightbox';
        lb.setAttribute('role', 'dialog');
        lb.setAttribute('aria-modal', 'true');
        lb.setAttribute('aria-label', 'Image viewer');
        lb.style.cssText = [
            'display:none',
            'position:fixed', 'inset:0', 'z-index:99999',
            'background:rgba(0,0,0,.92)',
            'align-items:center', 'justify-content:center',
        ].join(';');

        /* Close button */
        var btnClose = document.createElement('button');
        btnClose.innerHTML = '&times;';
        btnClose.setAttribute('aria-label', 'Close');
        btnClose.style.cssText = [
            'position:absolute', 'top:16px', 'right:20px',
            'background:none', 'border:none',
            'color:#fff', 'font-size:2.4rem', 'line-height:1',
            'cursor:pointer', 'opacity:.8', 'z-index:2',
            'padding:0 6px', 'font-family:sans-serif',
        ].join(';');
        btnClose.addEventListener('mouseover',  function () { this.style.opacity = '1'; });
        btnClose.addEventListener('mouseout',   function () { this.style.opacity = '.8'; });
        lb.appendChild(btnClose);

        /* Prev button */
        var btnPrev = document.createElement('button');
        btnPrev.innerHTML = '&#8249;';
        btnPrev.setAttribute('aria-label', 'Previous image');
        btnPrev.style.cssText = [
            'position:absolute', 'left:12px', 'top:50%', 'transform:translateY(-50%)',
            'background:rgba(255,255,255,.15)', 'border:none', 'border-radius:50%',
            'color:#fff', 'font-size:2.2rem', 'line-height:1',
            'width:48px', 'height:48px', 'cursor:pointer', 'z-index:2',
            'transition:background .15s',
        ].join(';');
        btnPrev.addEventListener('mouseover', function () { this.style.background = 'rgba(255,255,255,.3)'; });
        btnPrev.addEventListener('mouseout',  function () { this.style.background = 'rgba(255,255,255,.15)'; });

        /* Next button */
        var btnNext = document.createElement('button');
        btnNext.innerHTML = '&#8250;';
        btnNext.setAttribute('aria-label', 'Next image');
        btnNext.style.cssText = btnPrev.style.cssText
            .replace('left:12px', 'right:12px')
            .replace('left:12px;', '');
        btnNext.style.left  = '';
        btnNext.style.right = '12px';
        btnNext.addEventListener('mouseover', function () { this.style.background = 'rgba(255,255,255,.3)'; });
        btnNext.addEventListener('mouseout',  function () { this.style.background = 'rgba(255,255,255,.15)'; });

        /* Counter label  "2 / 5" */
        var counter = document.createElement('div');
        counter.style.cssText = [
            'position:absolute', 'bottom:14px', 'left:50%', 'transform:translateX(-50%)',
            'color:rgba(255,255,255,.7)', 'font-size:.9rem',
            'font-family:sans-serif', 'pointer-events:none',
        ].join(';');

        /* Full-size image */
        var lbImg = document.createElement('img');
        lbImg.style.cssText = [
            'max-width:92vw', 'max-height:88vh',
            'object-fit:contain', 'border-radius:4px',
            'box-shadow:0 4px 32px rgba(0,0,0,.6)',
            'user-select:none', '-webkit-user-drag:none',
        ].join(';');

        lb.appendChild(btnPrev);
        lb.appendChild(lbImg);
        lb.appendChild(btnNext);
        lb.appendChild(counter);
        document.body.appendChild(lb);

        /* Hide prev/next when only one image */
        function updateNavVisibility() {
            var multi = images.length > 1;
            btnPrev.style.display  = multi ? '' : 'none';
            btnNext.style.display  = multi ? '' : 'none';
            counter.style.display  = multi ? '' : 'none';
        }
        updateNavVisibility();

        /* ── Lightbox open / close / navigate ─────────────────────────── */

        var lbCurrent = 0;

        function lbShow(idx) {
            lbCurrent = ((idx % images.length) + images.length) % images.length;
            lbImg.src = images[lbCurrent];
            counter.textContent = (lbCurrent + 1) + ' / ' + images.length;
            lb.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function lbClose() {
            lb.style.display = 'none';
            document.body.style.overflow = '';
            lbImg.src = '';
        }

        function lbNext() { lbShow(lbCurrent + 1); activate(lbCurrent); }
        function lbPrev() { lbShow(lbCurrent - 1); activate(lbCurrent); }

        /* Open on main image click */
        if (mainImg) {
            mainImg.style.cursor = 'zoom-in';
            mainImg.addEventListener('click', function () { lbShow(current); });
        }

        btnClose.addEventListener('click', lbClose);
        btnPrev.addEventListener('click',  function (e) { e.stopPropagation(); lbPrev(); });
        btnNext.addEventListener('click',  function (e) { e.stopPropagation(); lbNext(); });

        /* Click on backdrop (outside image) closes */
        lb.addEventListener('click', function (e) {
            if (e.target === lb) lbClose();
        });

        /* Keyboard navigation */
        document.addEventListener('keydown', function (e) {
            if (lb.style.display !== 'flex') return;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') lbNext();
            if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')   lbPrev();
            if (e.key === 'Escape')                               lbClose();
        });

        /* Touch swipe support */
        var touchStartX = 0;
        lb.addEventListener('touchstart', function (e) {
            touchStartX = e.changedTouches[0].clientX;
        }, { passive: true });
        lb.addEventListener('touchend', function (e) {
            var dx = e.changedTouches[0].clientX - touchStartX;
            if (Math.abs(dx) > 40) { dx < 0 ? lbNext() : lbPrev(); }
        }, { passive: true });
    });
}());
