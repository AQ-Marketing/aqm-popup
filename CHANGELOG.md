# Changelog

## 1.1.2 — 2026-06-25

- **Background-image overlay.** New **Image overlay color** + **Image overlay opacity** fields in *Popup style*. When a background image is set, this lays a tint between the image and your text (e.g. black at 0.4 to darken a photo) so the text stays readable. Opacity 0 = no overlay (default, so existing popups are unchanged). It's distinct from the dark backdrop "Overlay opacity" in Behavior. Implemented as a flat gradient layered over the image (no extra markup); reflected live in the preview.

## 1.1.1 — 2026-06-25

- **Popup background image.** New **Background image** field in *Popup style* — choose an image from the Media Library to fill the whole popup behind your text and button (scaled to cover, centered). The background color shows underneath while it loads or if removed. Tip: set the text color to contrast for legibility over a photo.
- Works on its own (a background-image-only popup gets a minimum height so it stays visible) or layered under a headline/text/button. This is separate from the top **Image** field (which sits flush at the top) and from the dark overlay backdrop.
- The image URL is escaped via `esc_url()`; the cover/position rules live in `popup.css`. Reflected live in the preview.
- **Close button can now sit outside the popup.** The "Distance from corner" field accepts **negative** numbers (down to -100), which float the X just past the top-right corner. To make this reliable, the popup's border and rounded corners now apply to the card itself rather than the outer container, so the container no longer clips an outside button. Shown live in the preview.

## 1.1.0 — 2026-06-25

- **Build the popup right in the settings — Divi is no longer required.** New **Content** section: choose an image from the Media Library, write a headline and a paragraph, and add an optional button (label + link + open-in-new-tab). Any field left empty is skipped, so an image-only or text-only popup works too.
- **New Popup style section:** background color, text color, button color, button text color, max width, inner padding, and text alignment — all with a live preview.
- **The live preview now shows your real popup** (image, headline, text, button, and colors) instead of a placeholder, updating as you type.
- **Breaking change:** the Divi Library layout picker and the "Edge-to-edge content" option were removed. Sites that were using a Divi layout will show nothing until the new Content fields are filled in (no errors — the popup simply stays hidden until there's content). The popup body is now rendered by the plugin itself; Divi-specific CSS was removed.
- Output is fully escaped (image via `wp_get_attachment_image`, text via `esc_html`, link via `esc_url`); colors are validated as hex and sizes are clamped.

## 1.0.21 — 2026-06-25

- **Redesigned settings page** ("Crimson & ink"). Light, wp-admin-native, easier to follow.
  - **Branded header** with a subtle three.js particle field, a live **status chip** (Live / Off / Test mode) that reflects your enable + test-mode checkboxes, and the running version.
  - **Three-column working area:** a sticky, scroll-spy **section nav** (left) that highlights the section you're in, the settings **panels** (center), and a sticky **live popup preview** (right).
  - **Live preview** mirrors the visual settings in real time — overlay opacity + padding, popup border + radius, and the close button's size, offset, background, color, and radius. "Replay" re-plays the open animation. The popup body still comes from your Divi layout.
  - **Sticky save bar** so "Save changes" is always reachable.
  - Restyled inputs, selects, checkboxes (crimson focus ring + accent), descriptions, and buttons.
- **Motion** uses GSAP (load reveal, nav indicator, preview) and three.js (header), all feature-detected. If the libraries are blocked, or you have "reduce motion" enabled, the page renders as a clean, fully functional static page. Animation is decorative only at the header; everything else conveys state.
- **No change to how settings save.** The WordPress Settings API form, nonce, sanitization, and trigger toggles are untouched — the redesign is layered on top and works with JavaScript disabled.

## 1.0.20 — 2026-06-25

- **Security hardening of the CSS-value fields** (Popup border, Close icon Background, Close icon Icon color). The admin sanitizer already stripped characters that could break out of the inline `<style>` block (`< > ; { } " ' \\`); it now also allowlists CSS functions. Only the color functions — `rgb()`, `rgba()`, `hsl()`, `hsla()` — are permitted. Any other function (most importantly `url()`, plus `image()` and legacy `expression()`) causes the value to be rejected and fall back to its default. This closes an admin-only vector where a CSS value like `url(https://example.com/x)` could load an external resource on every page.
- All documented examples still work unchanged: `5px solid #ffffff`, `2px dashed #c10f30`, `10px solid rgba(255,255,255,0.5)`, `transparent`, `rgba(0,0,0,0.55)`, `#ffffff`, etc.

## 1.0.19 — 2026-05-21

- **New Popup border + border-radius fields** in Behavior. Reliable border around the popup regardless of how Divi or Imagify mark up the inside.
  - **Popup border** — CSS `border` shorthand string. Examples: `5px solid #ffffff`, `2px dashed #c10f30`, `10px solid rgba(255,255,255,0.5)`. Leave empty for no border.
  - **Popup border radius (px)** — rounded corners on the popup container. When > 0, the container also gets `overflow: hidden` so the rendered image clips to the rounded corners.
- Implemented via the same inline `<style id="aqm-popup-inline-style">` block as the close-icon styling (consolidated). Targets `#aqm-popup-container` (specificity 1,0,0) so it sits tight around the rendered Divi content regardless of where Divi or Imagify put their own border CSS.
- **Why this exists:** with Imagify wrapping `<img>` in `<picture>` (and moving the `wp-image-XXX` class onto `<picture>`), Divi's per-module border CSS may miss the visible element entirely. Plus on Fullwidth Image modules with `width: auto` on the inner img (for aspect preservation), Divi's module-level border can land far from the visible image edge. Setting the border on the popup container sidesteps both problems.

## 1.0.18 — 2026-05-21

- **New Close icon section** in the AQM Popup settings page. Style the X button without writing Custom CSS.
  - **Button size (px)** — width + height. Icon scales proportionally (icon = size × 0.5). Default `36`.
  - **Distance from corner (px)** — top + right offset. Default `10`.
  - **Background** — any valid CSS color string. Use `transparent` for a bare X with drop-shadow halo (default), `rgba(0,0,0,0.55)` for the v1.0.0–v1.0.9 translucent dark circle, any hex / rgb / named color.
  - **Icon color** — any valid CSS color string. Default `#ffffff`.
  - **Border radius (px)** — set to half the button size for a circle, 0 for a square. Default `0`.
- Implementation: display class emits an inline `<style id="aqm-popup-close-style">` block targeting `#aqm-popup-close` (specificity 1,0,0), which reliably beats the class-based defaults in `popup.css`.
- CSS values are sanitized in the admin to strip characters that could break out of the inline `<style>` context (`< > ; { } " ' \\`). Empty results fall back to the default value rather than rendering broken CSS.

## 1.0.17 — 2026-05-21

- **Divi borders + box-shadows on images now render in the popup.** Plain CSS borders were already working (the plugin doesn't touch `border` anywhere), but the popup container had `overflow: hidden` carried over from the v1.0.11 no-scrollbar work, which clipped `box-shadow`, `outline`, and any decorative pseudo-elements that render outside the box. Container is now `overflow: visible`.
- The image itself can't overflow because the v1.0.16 `max-width`/`max-height` constraints still apply to the image directly. The overlay's own `overflow: hidden` still catches anything that extends beyond the viewport, so the no-scrollbar guarantee is intact.
- **Niche side effect:** if your popup content is a tall text block (not an image) that exceeds the available height, it will now overflow the container visibly rather than being clipped. For text-heavy popups, design content that fits, or override `.aqm-popup-container { overflow: hidden }` in Custom CSS.

## 1.0.16 — 2026-05-21

- **Image fits within the viewport — no bottom clipping.** Reintroduces aspect-preserving image scaling: `max-width` + `max-height` + `width: auto` + `height: auto` on images/videos inside the popup so tall content scales down to fit instead of being clipped by the overlay's `overflow: hidden`.
- **Container + image max-dimensions know about overlay padding.** Display class now sets CSS custom properties `--aqm-popup-max-h: calc(100vh - 2 × Vpx)` and `--aqm-popup-max-w: calc(100vw - 2 × Hpx)` on the overlay element. Container and image rules consume these via `var()`, so when you set overlay padding to e.g. 60px vertical, the popup content shrinks to fit within `viewport_height - 120px`. Image preserves aspect ratio while shrinking.
- **Trade-off (re-acknowledged from v1.0.11):** on a viewport much wider than the image's natural size, the image renders at its natural intrinsic size rather than filling the container width. This is the price of aspect-preservation when Divi's `width: 100%` would otherwise stretch the image vertically. Use a larger/wider source image if you want it bigger on desktops, or override `#aqm-popup-content img { width: 100% }` in Custom CSS to revert to stretch-behavior.

## 1.0.15 — 2026-05-21

- **Moved the padding setting from the section to the overlay.** v1.0.14's section-padding approach pushed the image *inside* the Divi section — if the section had any background color (very common with `et_pb_with_background`), the padded area showed as that background color (e.g., white) instead of the dark backdrop. The setting now insets the popup from the viewport edges by adding padding to `.aqm-popup-overlay` directly, so the dark backdrop fills the padded area.
- Renamed setting keys: `section_padding_*` → `overlay_padding_*`. Existing values get reset to 0 on update — re-enter them in **Behavior** if you had non-zero values configured.
- Container's `max-height` switched from `100vh` to `100%` so it adapts to the overlay's content area when overlay padding is set.
- Overlay now uses `box-sizing: border-box` so the overlay still spans the full viewport while padding pushes the popup inward.

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
