# Changelog

## 1.0.4 — 2026-05-21

- **Test mode** now re-arms the time-delay trigger after dismissal, so the popup can be opened as many times as you need on the same page load (previously, after dismissing once, only scroll/exit/click triggers could re-fire — delay was one-shot per page load).
- Tightened test-mode admin copy to spell out that frequency is fully ignored (no cooldown, no session cap) when test mode is on.

(Frequency was already bypassed in test mode in v1.0.2 — this release closes the time-delay gap so all four trigger types behave consistently during testing.)

## 1.0.3 — 2026-05-21

**Divi controls all popup styling.**

- The plugin's `.aqm-popup-container` no longer has a background, border, border-radius, box-shadow, max-width, or padding of its own. The popup body is now whatever your Divi Library layout looks like, top to bottom.
- Removed the **Popup max-width (px)** setting — size the popup via your Divi section's max-width instead (Section / Row settings → Sizing → Max Width).
- The plugin still controls the dark **backdrop opacity**, the **close icon**, and the **test-mode chrome** (those are positioning concerns that Divi can't address).
- Aria attributes tightened: dialog now uses `aria-label="Popup"` instead of a broken `aria-labelledby` pointer.

**Breaking change** — if you previously set a popup max-width via the plugin, that value is now ignored. Re-apply the max-width via your Divi section settings.

## 1.0.2 — 2026-05-21

- New **Test mode** section: pick a single page where the popup will appear persistently (no cooldown, no session cap), with the popup suppressed everywhere else while test mode is on.
- Page picker lists drafts as well as published pages, so popups can be previewed privately.
- Yellow **Test mode** badge + dashed outline on the popup container while test mode is active, so it's obvious the live site isn't seeing this.
- Test mode is independent of the master **Enable popup** toggle — you can preview before going live, or test changes without disturbing production.

## 1.0.1 — 2026-05-21

- Settings live under their own top-level admin menu (**AQM Popup**) instead of nested under Settings.

## 1.0.0 — 2026-05-21

Initial release.

- Renders a selected Divi Library layout as a site-wide popup.
- Four trigger types: time delay, scroll depth, exit intent (desktop), click selector.
- Configurable max-shows-per-session (sessionStorage) and post-dismissal cooldown in days (localStorage).
- Three dismissal paths: close icon (X), ESC key, click outside the popup. All three trigger the cooldown.
- Always-visible SVG close icon in the top-right of the popup container.
- Master enable/disable toggle — no frontend assets are loaded when disabled.
- Settings page under Settings → AQM Popup.
- Self-contained GitHub Tags updater pointed at `AQ-Marketing/aqm-popup`.
