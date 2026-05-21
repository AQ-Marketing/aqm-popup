# Changelog

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
