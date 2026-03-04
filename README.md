# All-in-One Optimizer

A lightweight, zero-dependency WordPress performance plugin that combines four modules under a single settings page.

---

## Features

### Debloat
Removes unnecessary WordPress output that adds weight to every page:
- Emoji scripts & styles
- jQuery Migrate
- XML-RPC
- `<meta name="generator">`
- wlwmanifest & RSD links
- REST API discovery links from `<head>`
- Shortlink
- Self-pingbacks
- Query strings from static assets
- WP Heartbeat (disabled on front-end, throttled in admin)
- oEmbed discovery links

Each item is individually toggleable.

### Autoptimize
Optimizes how scripts and styles are delivered — no file rewriting or caching layer needed:
- Add `defer` or `async` to `<script>` tags
- Move scripts to the footer
- Remove `type="text/css"` from `<link>` tags
- Per-handle exclude list (e.g. `jquery,woocommerce`)

### Lazy Load
Defers off-screen images, iframes, and background images using `IntersectionObserver`:
- Replaces `src` with `data-src`, `srcset` with `data-srcset`
- Supports inline `background-image` via `data-bg`
- `<noscript>` fallback for non-JS browsers
- Immediate-load fallback when `IntersectionObserver` is unavailable
- Skips the first image (LCP / hero image protection)
- Auto-detects and defers to conflicting plugins (Smush, EWWW, Rocket Lazy Load, etc.)

### Flying Pages
Pre-warms the next page using the `fetch` API when a user hovers over a link:
- Configurable hover delay (default 65ms)
- Skips slow connections and `Save-Data` mode automatically
- Fetched HTML is stored in `window.aioPageCache` and consumed instantly by the SPA module — no second request
- Uses `requestIdleCallback` so prefetch never blocks rendering
- Mobile touchstart support (optional)

### Flying Images
Animates images that appear on both the current and next page during SPA navigation:
- Captures image positions before each page swap
- Animates matching images from old position to new position using CSS transforms
- Caches decoded image bitmaps in memory to prevent re-decoding
- Respects `prefers-reduced-motion`
- Works with any theme — detects `<img>` tags dynamically

### SPA Navigation
Intercepts internal link clicks and swaps page content via `fetch` — no full page reload:
- Fade transition between pages
- Thin CSS progress bar (no dependencies)
- Consumes pre-warmed pages from Flying Pages cache
- Updates `<title>` and `<meta description>`
- `history.pushState` / `popstate` (back/forward) support
- Re-executes inline `<script>` tags in new content
- Fires `aio:before-navigate` and `aio:navigate` custom events
- Configurable content selector and URL exclude patterns
- Self-disables on JS errors — falls back to normal navigation

---

## Installation

1. Download or clone this repository into your WordPress plugins directory:
   ```
   wp-content/plugins/all-in-one-optimizer/
   ```
2. In the WordPress admin, go to **Plugins** and activate **All-in-One Optimizer**.
3. Go to **Settings → AIO Optimizer** to configure each module.

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Modern browser (Chrome, Firefox, Safari, Edge) for SPA & lazy load features

---

## Settings

All settings are under **Settings → AIO Optimizer**, organized into six tabs:

| Tab | Description |
|---|---|
| Debloat | Toggle each WordPress bloat removal individually |
| Autoptimize | Defer/async JS, footer loading, style tag cleanup, exclude list |
| Lazy Load | Images, iframes, background images, skip-first-image |
| Flying Pages | Enable/disable, hover delay, mobile support |
| Flying Images | Enable shared element image transitions |
| SPA | Enable/disable, content selector, URL exclude patterns |

---

## Escape Hatches

Add these HTML attributes to opt specific elements out:

| Attribute | Effect |
|---|---|
| `data-no-spa` on `<a>` | Forces a normal (full) page load for that link |
| `data-no-prefetch` on `<a>` | Skips Flying Pages prefetch for that link |

---

## JavaScript Events

The plugin fires custom events on `document` that themes and other plugins can hook into:

```js
// Fired just before content is swapped (Flying Images uses this to capture positions)
document.addEventListener('aio:before-navigate', (e) => {
    console.log('Leaving:', e.detail.from);
    console.log('Going to:', e.detail.to);
});

// Fired after content is swapped and the new page is visible
document.addEventListener('aio:navigate', (e) => {
    console.log('Arrived at:', e.detail.url);
    console.log('New title:', e.detail.title);
    // Re-init your own scripts here
});
```

---

## Customising the Progress Bar Colour

Override the CSS variable in your theme:

```css
#aio-progress {
    --aio-bar-color: #e74c3c; /* any colour */
}
```

---

## Compatibility

- **WooCommerce** — cart, checkout, and my-account pages are excluded from SPA navigation by default.
- **Caching plugins** — compatible with WP Rocket, W3 Total Cache, LiteSpeed Cache, etc.
- **Image optimizers** — lazy load auto-detects Smush, EWWW, Rocket Lazy Load and disables itself to avoid conflicts.
- **Theme agnostic** — uses generic CSS selectors; no hard-coded theme class names.

---

## File Structure

```
all-in-one-optimizer/
├── all-in-one-optimizer.php       # Plugin bootstrap
├── includes/
│   ├── class-aio-debloat.php      # Debloat module
│   ├── class-aio-autoptimize.php  # JS/CSS optimization
│   ├── class-aio-lazy-load.php    # Lazy load (PHP output buffer)
│   └── class-aio-spa.php          # Enqueues front-end scripts
├── admin/
│   ├── class-aio-admin.php        # Settings registration & sanitization
│   └── views/settings.php         # Admin settings page HTML
└── assets/
    ├── js/
    │   ├── flying-pages.js        # Fetch-based link prefetch
    │   ├── spa.js                 # SPA navigation engine
    │   ├── lazy-load.js           # IntersectionObserver lazy loader
    │   └── flying-images.js       # Shared element image transitions
    └── css/
        └── admin.css              # Admin page styles
```

---

## License

GPL-2.0-or-later — see [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
