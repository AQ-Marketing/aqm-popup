/**
 * AQM Popup — admin UI enhancements.
 *
 * Progressive enhancement only. The WordPress Settings API form is fully
 * functional without this file; here we:
 *   - regroup do_settings_sections() output into panels + a scroll-spy nav
 *   - drive a live popup preview from the visual settings
 *   - render a subtle three.js particle header
 *   - add GSAP reveal / indicator / preview motion
 *
 * Every use of GSAP and three.js is feature-detected. If a library is missing
 * (blocked CDN) or the user prefers reduced motion, the page stays clean and
 * usable with no animation.
 */
(function () {
    'use strict';

    var UI = (window.aqmPopupUi && window.aqmPopupUi.i18n) || {};
    var I = {
        enabled:      UI.enabled      || 'Live',
        disabled:     UI.disabled     || 'Off',
        testMode:     UI.testMode     || 'Test mode',
        previewLabel: UI.previewLabel || 'Live preview',
        replay:       UI.replay       || 'Replay'
    };

    function hasGSAP()    { return typeof window.gsap !== 'undefined'; }
    function reduceMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }
    function ready(fn) {
        if (document.readyState !== 'loading') { fn(); }
        else { document.addEventListener('DOMContentLoaded', fn); }
    }

    ready(function () {
        var root = document.querySelector('[data-aqm-ui]');
        if (!root) { return; }

        var animate = hasGSAP() && !reduceMotion();

        // Hide reveal targets up front (before paint) to avoid a flash, but only
        // when we can actually animate them back in.
        if (animate) {
            try { window.gsap.set('[data-aqm-reveal]', { opacity: 0, y: 14 }); } catch (e) { /* noop */ }
        }

        // Each enhancement is independent and guarded: a failure in one must
        // never abort the others, and must never leave reveal targets hidden.
        var cards = [];
        try { cards = buildSections(); } catch (e) { /* leave native markup as-is */ }

        try { if (cards.length) { initNav(cards, animate); } } catch (e) { /* noop */ }
        try { initMedia(); } catch (e) { /* noop */ }
        try { initPreview(animate); } catch (e) { /* noop */ }
        try { initStatusChip(); } catch (e) { /* noop */ }
        try { initHero(); } catch (e) { /* noop */ }

        // Reveal runs last and is itself guarded; if it can't run, make sure the
        // content we hid up front is visible again.
        try {
            initReveal(animate);
        } catch (e) {
            try { window.gsap.set('[data-aqm-reveal]', { opacity: 1, clearProps: 'transform' }); }
            catch (e2) { /* noop */ }
        }
    });

    /* ----------------------------------------------------------------
     * Regroup the Settings API output into panels and collect nav data.
     * ---------------------------------------------------------------- */
    function buildSections() {
        var host = document.querySelector('[data-aqm-sections]');
        var navList = document.querySelector('[data-aqm-nav-list]');
        if (!host || !navList) { return []; }

        var children = Array.prototype.slice.call(host.children);
        var actions = host.querySelector('.aqm-actions');
        var cards = [];
        var current = null;

        children.forEach(function (node) {
            if (node === actions || node.classList.contains('aqm-actions')) { return; }

            if (node.tagName === 'H2') {
                var idx = cards.length + 1;
                var section = document.createElement('section');
                section.className = 'aqm-card';
                section.id = 'aqm-sec-' + idx;

                var head = document.createElement('div');
                head.className = 'aqm-card__head';
                var num = document.createElement('span');
                num.className = 'aqm-card__num';
                num.textContent = idx;
                node.classList.add('aqm-card__title');
                head.appendChild(num);
                head.appendChild(node);

                var body = document.createElement('div');
                body.className = 'aqm-card__body';

                section.appendChild(head);
                section.appendChild(body);
                section._body = body;

                cards.push({ el: section, body: body, title: (node.textContent || '').trim(), id: section.id, num: idx });
                current = section;
            } else if (current) {
                current._body.appendChild(node);
            }
        });

        cards.forEach(function (c) { host.insertBefore(c.el, actions); });

        // Build the nav links.
        cards.forEach(function (c) {
            var li = document.createElement('li');
            var a = document.createElement('a');
            a.href = '#' + c.id;
            a.setAttribute('data-target', c.id);

            var num = document.createElement('span');
            num.className = 'aqm-nav__num';
            num.textContent = (c.num < 10 ? '0' : '') + c.num;
            var txt = document.createElement('span');
            txt.className = 'aqm-nav__txt';
            txt.textContent = c.title;

            a.appendChild(num);
            a.appendChild(txt);
            li.appendChild(a);
            navList.appendChild(li);
        });

        return cards;
    }

    /* ----------------------------------------------------------------
     * Scroll-spy nav + sliding indicator + smooth scroll.
     * ---------------------------------------------------------------- */
    function initNav(cards, animate) {
        var navList = document.querySelector('[data-aqm-nav-list]');
        var indicator = document.querySelector('[data-aqm-nav-indicator]');
        if (!navList) { return; }

        function moveIndicator(a) {
            if (!indicator || !a) { return; }
            var top = a.offsetTop;
            var h = a.offsetHeight;
            if (animate) {
                window.gsap.to(indicator, { y: top, height: h, duration: 0.32, ease: 'expo.out' });
            } else {
                indicator.style.transform = 'translateY(' + top + 'px)';
                indicator.style.height = h + 'px';
            }
        }

        function setActive(id) {
            var links = navList.querySelectorAll('a[data-target]');
            var active = null;
            links.forEach(function (a) {
                var on = a.getAttribute('data-target') === id;
                a.classList.toggle('is-active', on);
                if (on) { active = a; }
            });
            if (active) { moveIndicator(active); }
        }

        navList.addEventListener('click', function (e) {
            var a = e.target.closest('a[data-target]');
            if (!a) { return; }
            e.preventDefault();
            var id = a.getAttribute('data-target');
            var el = document.getElementById(id);
            if (el) {
                el.scrollIntoView({ behavior: reduceMotion() ? 'auto' : 'smooth', block: 'start' });
            }
            setActive(id);
        });

        // Active section follows the scroll position.
        if ('IntersectionObserver' in window) {
            var visible = {};
            var spy = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) { visible[en.target.id] = en.isIntersecting; });
                for (var i = 0; i < cards.length; i++) {
                    if (visible[cards[i].id]) { setActive(cards[i].id); break; }
                }
            }, { rootMargin: '-72px 0px -60% 0px', threshold: 0 });
            cards.forEach(function (c) { spy.observe(c.el); });
        }

        // Initialize and keep the indicator aligned on resize.
        setActive(cards[0].id);
        window.addEventListener('resize', function () {
            var active = navList.querySelector('a.is-active');
            if (active) { moveIndicator(active); }
        });
    }

    /* ----------------------------------------------------------------
     * Live preview — mirrors the content + visual settings onto a mini popup.
     * ---------------------------------------------------------------- */
    function initPreview(animate) {
        var form = document.querySelector('[data-aqm-shell]');
        var overlay = document.querySelector('[data-aqm-preview-overlay]');
        var popup = document.querySelector('[data-aqm-preview-popup]');
        var closeBtn = document.querySelector('[data-aqm-preview-close]');
        var replay = document.querySelector('[data-aqm-replay]');
        if (!form || !overlay || !popup) { return; }

        var pvImg = document.querySelector('[data-aqm-preview-img]');
        var pvBody = document.querySelector('[data-aqm-preview-body]');
        var pvHeading = document.querySelector('[data-aqm-preview-heading]');
        var pvText = document.querySelector('[data-aqm-preview-text]');
        var pvBtn = document.querySelector('[data-aqm-preview-btn]');
        var imgThumb = document.querySelector('[data-aqm-image-preview] img');

        function field(name) {
            return form.querySelector('[name="aqm_popup_settings[' + name + ']"]');
        }
        function num(name, def) {
            var el = field(name);
            if (!el) { return def; }
            var n = parseFloat(el.value);
            return isNaN(n) ? def : n;
        }
        function str(name, def) {
            var el = field(name);
            if (!el || el.value === '') { return def; }
            return el.value;
        }
        function clamp(n, lo, hi) { return Math.min(hi, Math.max(lo, n)); }

        function apply() {
            // ---- backdrop + container chrome ----
            var opacity = clamp(num('overlay_opacity', 0.7), 0, 1);
            overlay.style.background = 'rgba(0,0,0,' + opacity + ')';

            var padV = Math.max(0, num('overlay_padding_vertical', 0));
            var padH = Math.max(0, num('overlay_padding_horizontal', 0));
            overlay.style.padding = Math.min(46, padV * 0.18) + 'px ' + Math.min(46, padH * 0.18) + 'px';

            var border = str('popup_border', '');
            var radius = Math.max(0, num('popup_border_radius_px', 0));
            popup.style.border = border ? border : 'none';
            popup.style.borderRadius = (radius * 0.32) + 'px';
            popup.style.overflow = radius > 0 ? 'hidden' : 'visible';

            // ---- body styling ----
            popup.style.background = str('style_bg_color', '#ffffff');
            popup.style.color = str('style_text_color', '#1d2327');
            popup.style.textAlign = (str('style_align', 'center') === 'left') ? 'left' : 'center';
            if (pvBody) { pvBody.style.padding = clamp(num('style_padding', 32), 0, 96) * 0.4 + 'px'; }

            // ---- content ----
            imgThumb = document.querySelector('[data-aqm-image-preview] img');
            var imgUrl = imgThumb ? imgThumb.getAttribute('src') : '';
            if (pvImg) {
                if (imgUrl) { pvImg.src = imgUrl; pvImg.hidden = false; }
                else { pvImg.removeAttribute('src'); pvImg.hidden = true; }
            }
            if (pvHeading) {
                var heading = str('content_heading', '');
                pvHeading.textContent = heading;
                pvHeading.hidden = heading === '';
            }
            if (pvText) {
                var body = str('content_body', '');
                pvText.textContent = body;
                pvText.hidden = body === '';
            }
            if (pvBtn) {
                var label = str('content_button_label', '');
                var url = str('content_button_url', '');
                if (label !== '' && url !== '') {
                    pvBtn.textContent = label;
                    pvBtn.style.background = str('style_button_bg', '#c10f30');
                    pvBtn.style.color = str('style_button_text_color', '#ffffff');
                    pvBtn.hidden = false;
                } else {
                    pvBtn.hidden = true;
                }
            }

            if (closeBtn) {
                var f = 0.5;
                var size = clamp(num('close_size_px', 36), 16, 200);
                var offset = clamp(num('close_offset_px', 10), 0, 100);
                var cradius = clamp(num('close_border_radius_px', 0), 0, 100);
                closeBtn.style.width = (size * f) + 'px';
                closeBtn.style.height = (size * f) + 'px';
                closeBtn.style.top = (offset * f) + 'px';
                closeBtn.style.right = (offset * f) + 'px';
                closeBtn.style.background = str('close_background', 'transparent');
                closeBtn.style.color = str('close_icon_color', '#ffffff');
                closeBtn.style.borderRadius = (cradius * f) + 'px';
            }
        }

        function pulse() {
            if (animate) {
                window.gsap.fromTo(popup, { scale: 0.985 }, { scale: 1, duration: 0.4, ease: 'expo.out' });
            }
        }

        function playOpen() {
            if (!animate) { return; }
            window.gsap.killTweensOf([overlay, popup]);
            window.gsap.fromTo(overlay, { opacity: 0 }, { opacity: 1, duration: 0.28, ease: 'power2.out' });
            window.gsap.fromTo(popup,
                { y: 10, scale: 0.97, opacity: 0.5 },
                { y: 0, scale: 1, opacity: 1, duration: 0.44, ease: 'expo.out', clearProps: 'opacity' }
            );
        }

        apply();
        form.addEventListener('input', apply);
        form.addEventListener('change', function () { apply(); pulse(); });
        if (replay) { replay.addEventListener('click', playOpen); }

        // Open animation once on load.
        if (animate) { window.setTimeout(playOpen, 260); }
    }

    /* ----------------------------------------------------------------
     * Image field — WordPress Media Library picker.
     * ---------------------------------------------------------------- */
    function initMedia() {
        var fieldEl = document.querySelector('[data-aqm-image-field]');
        if (!fieldEl) { return; }
        var input = fieldEl.querySelector('[data-aqm-image-input]');
        var preview = fieldEl.querySelector('[data-aqm-image-preview]');
        var chooseBtn = fieldEl.querySelector('[data-aqm-image-choose]');
        var removeBtn = fieldEl.querySelector('[data-aqm-image-remove]');
        if (!input || !chooseBtn) { return; }

        // No wp.media (rare) — leave the saved value intact, just hide the button.
        if (!window.wp || !window.wp.media) { chooseBtn.style.display = 'none'; return; }

        function setImage(id, url) {
            input.value = id ? String(id) : '';
            if (preview) {
                if (url) {
                    var img = preview.querySelector('img') || document.createElement('img');
                    img.src = url;
                    img.alt = '';
                    if (!img.parentNode) { preview.appendChild(img); }
                    preview.hidden = false;
                } else {
                    preview.innerHTML = '';
                    preview.hidden = true;
                }
            }
            if (removeBtn) { removeBtn.hidden = !url; }
            // Nudge the live preview to re-read.
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }

        var frame = null;
        chooseBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (frame) { frame.open(); return; }
            frame = window.wp.media({ title: (UI.chooseImage || 'Choose popup image'), button: { text: (UI.useImage || 'Use this image') }, multiple: false, library: { type: 'image' } });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                var sizes = att.sizes || {};
                var pick = sizes.large || sizes.medium_large || sizes.medium || null;
                setImage(att.id, pick ? pick.url : att.url);
            });
            frame.open();
        });
        if (removeBtn) {
            removeBtn.addEventListener('click', function (e) { e.preventDefault(); setImage(0, ''); });
        }
    }

    /* ----------------------------------------------------------------
     * Status chip reflects the enable / test-mode checkboxes live.
     * ---------------------------------------------------------------- */
    function initStatusChip() {
        var chip = document.querySelector('[data-aqm-status]');
        var form = document.querySelector('[data-aqm-shell]');
        if (!chip || !form) { return; }

        function box(name) { return form.querySelector('[name="aqm_popup_settings[' + name + ']"]'); }

        function update() {
            var enabled = box('enabled');
            var test = box('test_mode_enabled');
            var state = (test && test.checked) ? 'test' : ((enabled && enabled.checked) ? 'live' : 'off');
            chip.setAttribute('data-state', state);
            chip.className = 'aqm-chip aqm-chip--' + state;
            var txt = chip.querySelector('.aqm-chip__text');
            if (txt) { txt.textContent = state === 'test' ? I.testMode : (state === 'live' ? I.enabled : I.disabled); }
        }

        form.addEventListener('change', update);
        update();
    }

    /* ----------------------------------------------------------------
     * three.js particle header (subtle, brand-tinted, pauses when hidden).
     * ---------------------------------------------------------------- */
    function initHero() {
        var canvas = document.querySelector('[data-aqm-hero-canvas]');
        if (!canvas) { return; }
        var THREE = window.THREE;
        if (!THREE) { canvas.style.display = 'none'; return; }

        var hero = canvas.parentElement;
        var renderer;
        try {
            renderer = new THREE.WebGLRenderer({ canvas: canvas, alpha: true, antialias: true });
        } catch (e) {
            canvas.style.display = 'none';
            return;
        }
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

        var scene = new THREE.Scene();
        var camera = new THREE.PerspectiveCamera(58, 1, 0.1, 100);
        camera.position.z = 26;

        var COUNT = 150;
        var positions = new Float32Array(COUNT * 3);
        var colors = new Float32Array(COUNT * 3);
        var crimson = new THREE.Color(0xe23b54);
        var pink = new THREE.Color(0xf7c9cf);
        var white = new THREE.Color(0xffffff);
        for (var i = 0; i < COUNT; i++) {
            positions[i * 3]     = (Math.random() - 0.5) * 64;
            positions[i * 3 + 1] = (Math.random() - 0.5) * 30;
            positions[i * 3 + 2] = (Math.random() - 0.5) * 24;
            var m = Math.random();
            var c = m < 0.6 ? crimson : (m < 0.85 ? pink : white);
            colors[i * 3]     = c.r;
            colors[i * 3 + 1] = c.g;
            colors[i * 3 + 2] = c.b;
        }
        var geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geo.setAttribute('color', new THREE.BufferAttribute(colors, 3));
        var mat = new THREE.PointsMaterial({
            size: 0.7,
            vertexColors: true,
            transparent: true,
            opacity: 0.85,
            sizeAttenuation: true,
            depthWrite: false,
            blending: THREE.AdditiveBlending
        });
        var points = new THREE.Points(geo, mat);
        scene.add(points);

        function resize() {
            var w = hero.clientWidth;
            var h = hero.clientHeight;
            if (!w || !h) { return; }
            renderer.setSize(w, h, false);
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
        }
        resize();
        if (window.ResizeObserver) { new ResizeObserver(resize).observe(hero); }
        else { window.addEventListener('resize', resize); }

        var mx = 0, my = 0, tx = 0, ty = 0;
        hero.addEventListener('pointermove', function (e) {
            var r = hero.getBoundingClientRect();
            mx = (e.clientX - r.left) / r.width - 0.5;
            my = (e.clientY - r.top) / r.height - 0.5;
        });
        hero.addEventListener('pointerleave', function () { mx = 0; my = 0; });

        var raf = null;
        function frame() {
            points.rotation.y += 0.0007;
            points.rotation.x = Math.sin(points.rotation.y * 0.5) * 0.06;
            tx += (mx * 3 - tx) * 0.05;
            ty += (-my * 2 - ty) * 0.05;
            camera.position.x = tx;
            camera.position.y = ty;
            camera.lookAt(0, 0, 0);
            renderer.render(scene, camera);
            raf = window.requestAnimationFrame(frame);
        }

        if (reduceMotion()) {
            camera.lookAt(0, 0, 0);
            renderer.render(scene, camera);
        } else {
            frame();
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    if (raf) { window.cancelAnimationFrame(raf); raf = null; }
                } else if (!raf) {
                    frame();
                }
            });
        }
    }

    /* ----------------------------------------------------------------
     * GSAP load reveal. Clears the transform on complete so the sticky
     * nav / aside / save bar keep working (a lingering transform would
     * break position: sticky).
     * ---------------------------------------------------------------- */
    function initReveal(animate) {
        if (!animate) { return; }
        var els = document.querySelectorAll('[data-aqm-reveal]');
        if (!els.length) { return; }
        window.gsap.to(els, {
            opacity: 1,
            y: 0,
            duration: 0.5,
            ease: 'expo.out',
            stagger: 0.07,
            clearProps: 'transform'
        });
    }
})();
