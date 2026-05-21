# AQM Popup

A lightweight site-wide popup plugin for Divi 4 sites. Renders an existing **Divi Library layout** as the popup content, so you build the popup with the Divi Builder — no separate WYSIWYG.

## Requirements

- WordPress 5.2+
- PHP 7.2+
- Divi theme (or any theme that registers the `et_pb_layout` post type)

## Install

1. Zip the `aqm-popup` folder.
2. WordPress admin → Plugins → Add New → Upload Plugin → choose the zip → Install Now → Activate.

## Configure

WP Admin → **AQM Popup**.

### General

| Setting | Notes |
|---|---|
| **Enable popup** | Master on/off. When off, no frontend assets are enqueued. |
| **Divi Library layout** | Pick a layout from Divi → Divi Library. Its rendered output becomes the popup body. |

### Triggers

Enable any combination. The popup appears as soon as the **first** enabled trigger fires.

- **Time delay** — show after N seconds on the page.
- **Scroll depth** — show after the visitor has scrolled N% of the page.
- **Exit intent** — show when the cursor moves toward the top of the viewport (desktop only — automatically skipped on touch devices).
- **Click selector** — show when the visitor clicks an element matching a CSS selector (e.g. `.open-popup, #cta-button`). If the matched element is an `<a>`, its navigation is suppressed.

### Frequency

- **Max shows per session** — how many times the popup can appear in a single browser session. Resets when the tab closes.
- **Cooldown after dismissal (days)** — once dismissed, the popup is suppressed for this many days. Stored in `localStorage`, so it persists across sessions. Set to `0` to disable the cooldown.

### Test mode

For previewing the popup without going live to your visitors:

- **Enable test mode** — when on, the popup shows **only** on the selected test page below, and ignores all frequency caps. It does **not** show on any other page.
- **Test page** — pick a page. The dropdown includes drafts (so you can test on a private page that only logged-in editors can see).

Test mode is independent of the master **Enable popup** toggle, so you can preview before going live or test new triggers without affecting your production popup. The popup renders identically in test mode and production — what you see during preview is exactly what visitors will see.

### Behavior

- **Close on click outside** — clicking the dark overlay dismisses the popup (and starts the cooldown).
- **Close on ESC key** — pressing ESC dismisses.
- **Overlay opacity** — the dark backdrop behind the popup, 0 (transparent) to 1 (opaque black).

The close icon (X) in the top-right is always visible.

## Styling the popup

All visual properties of the popup body — **background, padding, margins, border, border-radius, max-width, box-shadow, typography** — come from your selected Divi Library layout. The plugin contributes nothing to the popup's appearance except the dark backdrop and the close icon.

In practice:

- **Width** — set on your Divi section or row (Section settings → Design → Sizing → Max Width). The popup wraps to your section's width.
- **Padding / margins** — use Divi's padding/margin controls on the section, row, or modules inside.
- **Border + border-radius** — set on the section (Design → Border).
- **Background** — set on the section's Content → Background.
- **Shadow** — set on the section (Design → Box Shadow).
- **Typography** — Divi modules carry their own typography settings.

If you want the popup to look like a typical centered modal card, build your Divi layout with a single Section that has a white background, a border-radius (e.g. 8px), a soft box-shadow, internal padding (e.g. 40px), and a max-width (e.g. 600px). The plugin will center that section in the viewport with the dark backdrop behind it.

### Overriding the plugin's chrome with custom CSS

If you need to restyle the close icon:

```css
/* in your Divi Theme Options → Custom CSS */
.aqm-popup-close { background: rebeccapurple; }
```

## How it works

The plugin renders a hidden overlay in the page footer that contains your selected Divi Library layout, processed through `apply_filters('the_content', …)` so all `et_pb_*` shortcodes resolve correctly. A small vanilla-JS script (no jQuery on the frontend) wires up the triggers and storage-based frequency caps.

### Storage keys

| Key | Scope | Purpose |
|---|---|---|
| `aqm_popup_dismissed_at` | `localStorage` | Unix-ms timestamp of the last dismissal. Used for the cooldown check. |
| `aqm_popup_shown_count` | `sessionStorage` | Per-session show counter. Resets when the browser tab closes. |

To reset for a single visitor (e.g. during QA), open DevTools → Application → Storage and delete those two keys.

## Limitations (v1)

- One popup site-wide. No per-page targeting and no support for multiple popups — every enabled page sees the same one.
- Exit intent is desktop-only.
- The popup body inherits whatever Divi assets are already loaded on the page. If your Library layout uses a Divi module that no other content on the page uses, its CSS may not be enqueued — pick layouts that use common modules (Section / Row / Text / Button / Image), or activate Divi's "Static CSS File Generation" to make module CSS available globally.

## Updates

The plugin checks the latest tag on `https://github.com/AQ-Marketing/aqm-popup` every 6 hours and surfaces available updates on the WP Plugins screen. Tag names like `v1.0.1` and `1.0.1` are both accepted.

## License

GPL-2.0-or-later.
