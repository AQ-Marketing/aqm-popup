(function () {
    'use strict';

    var STORAGE = {
        DISMISSED_AT: 'aqm_popup_dismissed_at',
        SHOWN_COUNT: 'aqm_popup_shown_count'
    };

    var DAY_MS = 86400000;

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function safeGetLocal(key) {
        try { return window.localStorage.getItem(key); } catch (e) { return null; }
    }
    function safeSetLocal(key, val) {
        try { window.localStorage.setItem(key, val); } catch (e) { /* ignore */ }
    }
    function safeGetSession(key) {
        try { return window.sessionStorage.getItem(key); } catch (e) { return null; }
    }
    function safeSetSession(key, val) {
        try { window.sessionStorage.setItem(key, val); } catch (e) { /* ignore */ }
    }

    function getShownCount() {
        var n = parseInt(safeGetSession(STORAGE.SHOWN_COUNT) || '0', 10);
        return isNaN(n) ? 0 : n;
    }
    function setShownCount(n) {
        safeSetSession(STORAGE.SHOWN_COUNT, String(n));
    }

    function isInCooldown(cooldownDays) {
        if (!cooldownDays || cooldownDays <= 0) return false;
        var raw = safeGetLocal(STORAGE.DISMISSED_AT);
        if (!raw) return false;
        var ts = parseInt(raw, 10);
        if (isNaN(ts) || ts <= 0) return false;
        return (Date.now() - ts) < (cooldownDays * DAY_MS);
    }

    function isTouchDevice() {
        return ('ontouchstart' in window) || (navigator.maxTouchPoints && navigator.maxTouchPoints > 0);
    }

    ready(function () {
        var settings = window.aqmPopupSettings;
        if (!settings || !settings.triggers) return;

        var overlay = document.getElementById('aqm-popup-overlay');
        var container = document.getElementById('aqm-popup-container');
        var closeBtn = document.getElementById('aqm-popup-close');
        if (!overlay || !container || !closeBtn) return;

        var freq = settings.frequency || { maxPerSession: 1, cooldownDays: 7 };
        var behavior = settings.behavior || { closeOnOverlayClick: true, closeOnEsc: true };
        var triggers = settings.triggers || {};
        var testMode = settings.testMode === true;

        if (!testMode) {
            if (isInCooldown(freq.cooldownDays)) return;
            if (getShownCount() >= freq.maxPerSession) return;
        }

        var teardownFns = [];
        var isOpen = false;
        var firedOnce = false;

        function teardownAllTriggers() {
            while (teardownFns.length) {
                var fn = teardownFns.pop();
                try { fn(); } catch (e) { /* ignore */ }
            }
        }

        function showPopup() {
            if (isOpen || firedOnce) return;
            if (!testMode && isInCooldown(freq.cooldownDays)) {
                teardownAllTriggers();
                return;
            }
            firedOnce = true;
            isOpen = true;

            overlay.hidden = false;
            // force a frame so the transition runs
            void overlay.offsetWidth;
            overlay.classList.add('is-open');
            document.body.classList.add('aqm-popup-open');

            if (!testMode) {
                var newCount = getShownCount() + 1;
                setShownCount(newCount);
                if (newCount >= freq.maxPerSession) {
                    teardownAllTriggers();
                }
            }

            // focus management for accessibility
            try { closeBtn.focus({ preventScroll: true }); } catch (e) { closeBtn.focus(); }
        }

        function dismissPopup() {
            if (!isOpen) return;
            isOpen = false;
            firedOnce = false; // re-arm in test mode so subsequent triggers still fire on this page load
            overlay.classList.remove('is-open');
            document.body.classList.remove('aqm-popup-open');
            // hide after transition
            window.setTimeout(function () {
                if (!isOpen) overlay.hidden = true;
            }, 220);
            if (!testMode) {
                safeSetLocal(STORAGE.DISMISSED_AT, String(Date.now()));
                teardownAllTriggers();
            }
        }

        // ----- dismiss handlers -----
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            dismissPopup();
        });

        if (behavior.closeOnOverlayClick) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) dismissPopup();
            });
        }

        if (behavior.closeOnEsc) {
            document.addEventListener('keydown', function (e) {
                if (isOpen && (e.key === 'Escape' || e.keyCode === 27)) {
                    dismissPopup();
                }
            });
        }

        // ----- triggers -----

        if (triggers.delay && typeof triggers.delay.seconds === 'number') {
            var timer = window.setTimeout(showPopup, Math.max(0, triggers.delay.seconds) * 1000);
            teardownFns.push(function () { window.clearTimeout(timer); });
        }

        if (triggers.scroll && typeof triggers.scroll.percent === 'number') {
            var threshold = Math.min(100, Math.max(1, triggers.scroll.percent));
            var onScroll = function () {
                var doc = document.documentElement;
                var scrolled = window.pageYOffset || doc.scrollTop || 0;
                var viewport = window.innerHeight || doc.clientHeight || 0;
                var full = Math.max(doc.scrollHeight, doc.offsetHeight, document.body ? document.body.scrollHeight : 0);
                if (full <= viewport) {
                    // page is shorter than viewport — trigger immediately
                    showPopup();
                    return;
                }
                var pct = ((scrolled + viewport) / full) * 100;
                if (pct >= threshold) showPopup();
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            teardownFns.push(function () { window.removeEventListener('scroll', onScroll); });
        }

        if (triggers.exit === true) {
            // Skip exit-intent on touch/mobile — no equivalent gesture.
            if (!isTouchDevice() && window.innerWidth >= 768) {
                var onMouseLeave = function (e) {
                    // Only fire when the cursor leaves out the top of the viewport.
                    if (e.clientY <= 10) showPopup();
                };
                document.addEventListener('mouseleave', onMouseLeave);
                teardownFns.push(function () { document.removeEventListener('mouseleave', onMouseLeave); });
            }
        }

        if (triggers.click && typeof triggers.click.selector === 'string' && triggers.click.selector.trim() !== '') {
            var selector = triggers.click.selector.trim();
            var onClick = function (e) {
                var target = e.target && e.target.nodeType === 1 ? e.target : null;
                if (!target) return;
                var matched = null;
                try {
                    matched = target.closest(selector);
                } catch (err) {
                    // invalid selector — bail silently
                    return;
                }
                if (!matched) return;
                // Don't intercept clicks happening inside the popup itself.
                if (overlay.contains(matched)) return;
                if (matched.tagName === 'A') e.preventDefault();
                showPopup();
            };
            document.addEventListener('click', onClick);
            teardownFns.push(function () { document.removeEventListener('click', onClick); });
        }
    });
})();
