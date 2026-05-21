# Changelog

## 1.0.9 — 2026-05-21

- New **Edge-to-edge content** setting in **Behavior**. When on, the plugin zeroes out Divi's default section padding (4% top/bottom), row padding (27px top/bottom), and image-module bottom margin inside the popup. Use it for popups that should be a single edge-to-edge image or full-bleed content without having to manually zero those values on every Library layout.
- Default is OFF — Divi defaults apply, just like before. Per-section padding/margin you explicitly set in the Divi UI continues to take effect (Divi generates per-section CSS at equal or higher specificity, so it wins over this reset).
- Only affects rendering inside `#aqm-popup-overlay.aqm-popup-edge-to-edge` — the rest of the site is untouched.

## 1.0.8 — 2026-05-21

- Reset Divi's auto-generated layout wrappers (`.et-l`, `.et_builder_inner_content`) to zero padding/margin/background inside the popup. These wrappers are injected by Divi when a Library layout is rendered via `apply_filters('the_content', …)` and can't be reached from the Divi UI, so the user otherwise has no way to zero out any spacing they impose.
- Sections, rows, columns, and modules are still 100% user-controlled via Divi Builder — those are not touched.

## 1.0.7 — 2026-05-21

- New **Check for plugin updates now** button at the bottom of the AQM Popup settings page. Clears the plugin's 6-hour GitHub-data cache *and* WordPress's `update_plugins` site transient, then forces a fresh poll. The result (available version vs. current version) is displayed inline next to the button.
- GitHub username and repo factored out to `AQM_POPUP_GH_USER` / `AQM_POPUP_GH_REPO` constants so the updater + the manual check share the same transient key.

## 1.0.6 — 2026-05-21

- Removed the **Test mode** visual indicators (yellow badge + dashed outline). The popup now renders identically in test mode and production — what you preview is exactly what visitors will see.
- Test-mode **behavior** is unchanged: page restriction, frequency bypass (cooldown + session cap ignored), time-delay re-arm on dismissal, and console diagnostics all stay.
- Cleaner CSS — `.aqm-popup-test-badge` and `.aqm-popup-overlay.is-test-mode` rules removed.

## 1.0.5 — 2026-05-21

- **Click trigger** now listens in **capture phase** on the document, so clicks are caught before any module (Divi or otherwise) can call `event.stopPropagation()` to swallow them. This is the most common reason a click trigger silently fails on Divi pages.
- **Console diagnostics in test mode.** While test mode is on, the plugin logs to the browser DevTools console (F12 → Console):
  - Init line with the configured triggers, frequency, and test-mode state.
  - Validation of the click selector at page load (how many elements currently match, or a warning if the selector is invalid CSS).
  - Every click event seen by the document, with the target element and whether it matched the configured selector.
  - When the popup shows or when a `showPopup()` call is skipped because it already fired.
- Updated the click-trigger admin help to spell out the DevTools debugging workflow.

The live site stays silent — these logs only emit when **test mode** is enabled.

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
