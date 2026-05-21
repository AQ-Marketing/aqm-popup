# Changelog

## 1.0.14 — 2026-05-21

- **New Section padding fields** under Behavior — top/bottom (px) and left/right (px). Forces padding on every Divi section inside the popup, with `!important` specificity, scoped to `#aqm-popup-content .et_pb_section`. Bypasses Divi's lock that prevents the Divi-UI padding control from working on **Fullwidth Sections** (`.et_pb_fullwidth_section { padding: 0 }` in Divi's base CSS).
- The padding is applied to the **Divi section element itself**, NOT the overlay — your section's background, border, and other section-level styling still cover the padding area.
- Leave at 0 to let Divi UI control section padding (the v1.0.12 behavior — perfect when using a Regular Section).

## 1.0.13 — 2026-05-21

**Stop interfering with Divi's image sizing.**

v1.0.11 added image-scaling rules (`width: auto`, `height: auto`, `max-height: 100vh`, `object-fit: contain` on `<img>`, `<picture>`, `<video>`) to satisfy the no-scrollbar request. Side effect: `width: auto` overrides Divi's own `.et_pb_fullwidth_image img { width: 100% }` rule, so Fullwidth Image modules rendered at their natural intrinsic size instead of filling the popup container. Users perceived this as "Divi styles aren't working" — section padding looked ignored because the image was visibly smaller than the container.

- **Removed `width: auto`, `height: auto`, `max-height`, and `object-fit` from the image rules.** Divi now controls image sizing fully — Fullwidth Image modules fill their container, regular image modules render at their configured Divi size, and section/row padding visibly affects image position.
- **Kept** `#aqm-popup-content img, video { max-width: 100% }` as a standard responsive guard against horizontal overflow.
- **Trade-off:** the overlay + container still use `overflow: hidden` + `max-height: 100vh` (per v1.0.11's no-scrollbar request), so content taller than the viewport clips at the bottom. Design popup content that fits the viewport — or override `.aqm-popup-container { overflow: auto }` in Divi Custom CSS if you want scrolling back.

## 1.0.12 — 2026-05-21

**Hotfix.** v1.0.11's `:where()` change to make Divi UI overrides win in edge-to-edge mode dropped selector specificity to `0,1,0`, which lost the cascade battle against Divi's `.et_pb_row.et_pb_equal_columns` rule (specificity `0,2,0`). The row's default 80% width came back, putting ~10% of empty section on each side of edge-to-edge content — visible as a ~100px white border.

- **Reverted to direct selectors** (specificity `0,2,0`) for row/column/module so they reliably beat Divi's compound base rules.
- **Removed section padding from the edge-to-edge reset.** Section padding is now purely controlled by **Divi UI** (Section → Design → Spacing → Padding). Set it to 0 for a true edge-to-edge image, or any value (e.g., 40px) for a card-style popup with breathing room. This is more honest to the "Divi controls all popup styling" principle and avoids the specificity battle entirely for the one element users most commonly want to tune.
- **Kept** the v1.0.11 no-scrollbar + image-scaling fixes (those were not the problem).

## 1.0.11 — 2026-05-21

- **No scrollbars in the popup.** Overlay and container now use `overflow: hidden` instead of `overflow-y: auto`. Images, `<picture>`, and `<video>` elements inside the popup scale to fit the viewport via `max-height: 100vh` + `object-fit: contain`, so tall media no longer triggers a scrollbar — it just shrinks. Text-heavy content that exceeds the viewport will clip rather than scroll, so design your popup layouts to fit.
- **Divi UI overrides now win in edge-to-edge mode.** The edge-to-edge mode's CSS selectors are now wrapped in `:where()`, which keeps their specificity at `0,1,0` — tied with Divi's per-section/per-row generated CSS. Divi prints its per-element rules after our stylesheet, so anything you set explicitly in Divi UI (Section → Design → Spacing → Padding, Row width via Custom Width, etc.) wins over the plugin's reset. Practical upshot: turn edge-to-edge mode on for a sensible flush default, and add padding back per-layout in Divi when you want it.

## 1.0.10 — 2026-05-21

- **Edge-to-edge content** now also forces Divi rows to `width: 100%` (Divi default is 80%). This was the main cause of "image still has a white strip on either side after enabling edge-to-edge mode" — the row width default was leaving 10% empty on each side.
- **Close icon** no longer has a hard-coded dark circle background. It's now a plain white X with a drop-shadow halo for visibility on both light and dark content. Override `.aqm-popup-close` via Divi → Custom CSS if you want a circle, square, brand color, etc.
- Combined with v1.0.9's padding reset, the close icon now overlaps the top-right corner of your actual Divi content when edge-to-edge mode is on, because there's no longer any phantom white space between the container and the content.

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
