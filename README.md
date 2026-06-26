# AQM Popup

A lightweight site-wide popup plugin. Build popups right in the settings — image, headline, text, button, colors, and fonts — keep a **library of designs**, **schedule** them by date, and switch which one is live at any time. Live preview included. No page builder required.

## Requirements

- WordPress 5.2+
- PHP 7.2+

## Install

1. Zip the `aqm-popup` folder.
2. WordPress admin → Plugins → Add New → Upload Plugin → choose the zip → Install Now → Activate.

## Configure

WP Admin → **AQM Popup**. The page has a **Designs** manager, a per-design editor with a sticky section nav, and a **live preview** that mirrors the design you're editing.

### Designs

- A library of popup designs. Each design is a full preset — its own content, style, typography, triggers, frequency, behavior, and close-icon.
- **Activate** sets which one design is live. **Archive** sets one aside. **Duplicate** copies one. **Add** makes a new one. **Edit** loads a design into the editor below.
- The master **Popup enabled site-wide** switch turns the whole popup on/off. When off, no frontend assets are enqueued.
- Adding / activating / switching designs reloads the page, so save your edits first.

### This design (name + schedule)

- **Design name** — for your reference in the Designs list.
- **Start date / End date** — optional. The active design only shows within this window (your site's timezone). Leave empty for no limit. Outside the window, no popup shows.

### Content

| Setting | Notes |
|---|---|
| **Image** | Choose an image from the Media Library. Sits flush at the top of the popup. Optional. |
| **Headline** | Bold title at the top of the text. Optional. |
| **Text** | A short paragraph. Line breaks are preserved. Optional. |
| **Button label / link** | A call-to-action button. Both a label and a link are required for the button to show. Optionally open the link in a new tab. |

Any field left empty is skipped — an image-only, text-only, or background-image-only popup all work.

### Popup style

Background color, **background image** (with an optional **overlay tint** for legibility), text color, button colors, max width, **minimum height**, inner padding, **text alignment** (left / center / right), and **vertical alignment** (top / center / bottom — needs a minimum height or background image to have room to work). Colors use a native color picker; everything updates in the live preview.

### Typography

Choose a **base font** — Theme default (your site's font, no extra request) or a Google Font (Inter, Poppins, Montserrat, Roboto, Lato, Open Sans, Playfair Display, Merriweather). Set the **text size + weight**, and the headline has a full set of its own controls: **font** (override the base), **size**, **weight**, optional **custom color**, **line height**, **letter spacing**, **letter case** (uppercase / lowercase / capitalize), **italic**, **alignment**, and **space below**. Google Fonts load automatically on the front end.

### Triggers

Enable any combination. The popup appears as soon as the **first** enabled trigger fires.

- **Time delay** — show after N seconds on the page.
- **Scroll depth** — show after the visitor has scrolled N% of the page.
- **Exit intent** — show when the cursor moves toward the top of the viewport (desktop only — automatically skipped on touch devices).
- **Click selector** — show when the visitor clicks an element matching a CSS selector (e.g. `.open-popup, #cta-button`). If the matched element is an `<a>`, its navigation is suppressed.

### Frequency

- **Max shows per session** — how many times the popup can appear in a single browser session. Resets when the tab closes.
- **Cooldown after dismissal (days)** — once dismissed, the popup is suppressed for this many days. Stored in `localStorage`, so it persists across sessions. Set to `0` to disable the cooldown.

### Behavior

- **Close on click outside** — clicking the dark overlay dismisses the popup (and starts the cooldown).
- **Close on ESC key** — pressing ESC dismisses.
- **Overlay opacity** — the dark backdrop behind the popup, 0 (transparent) to 1 (opaque black).
- **Overlay padding** — inset the popup from the viewport edges.
- **Popup border / border radius** — an optional border and rounded corners around the whole popup.

### Close icon

Style the X button: size, distance from the corner, background, icon color, and border radius.

### Test mode

For previewing the popup without going live to your visitors:

- **Enable test mode** — when on, the popup shows **only** on the selected test page below, and ignores all frequency caps. It does **not** show on any other page.
- **Test page** — pick a page. The dropdown includes drafts (so you can test on a private page that only logged-in editors can see).

Test mode is independent of the master **Enable popup** toggle, so you can preview before going live or test new triggers without affecting your production popup.

## How it works

The plugin renders a hidden overlay in the page footer containing the popup you built (image, headline, text, button). All output is escaped: the image via `wp_get_attachment_image()`, text via `esc_html()`, the link via `esc_url()`; colors are validated as hex and sizes are clamped. A small vanilla-JS script (no jQuery on the frontend) wires up the triggers and storage-based frequency caps.

### Storage keys

| Key | Scope | Purpose |
|---|---|---|
| `aqm_popup_dismissed_at` | `localStorage` | Unix-ms timestamp of the last dismissal. Used for the cooldown check. |
| `aqm_popup_shown_count` | `sessionStorage` | Per-session show counter. Resets when the browser tab closes. |

To reset for a single visitor (e.g. during QA), open DevTools → Application → Storage and delete those two keys.

## Limitations

- One **active** design shows at a time, site-wide (you can keep many designs in the library and switch between them). No per-page targeting — every enabled page shows the active design.
- Exit intent is desktop-only.

## Updates

The plugin checks the latest tag on `https://github.com/AQ-Marketing/aqm-popup` every 6 hours and surfaces available updates on the WP Plugins screen. Tag names like `v1.1.0` and `1.1.0` are both accepted.

## License

GPL-2.0-or-later.
