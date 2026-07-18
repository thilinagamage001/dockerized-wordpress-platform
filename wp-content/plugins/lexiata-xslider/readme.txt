=== Lexiata XSlider ===
Contributors: mrsureshlk
Tags: slider, image slider, responsive slider, carousel, lightweight
Requires at least: 5.5
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight, fast image slider for WordPress. Responsive sliders with clickable links — no bloat, no third-party dependencies.

== Description ==

**Lexiata XSlider** is a tiny, blazing-fast image slider plugin built from scratch. Total footprint: under 200KB on disk, with a frontend payload around 8KB (CSS + JS combined). No bundled libraries, no jQuery on the front-end, no third-party services.

**Why XSlider?**

Most slider plugins ship with hundreds of templates, layer animations, and features you will never use — and your visitors pay for every kilobyte. XSlider keeps only what you need:

* Clean image sliders with optional click-through URLs
* Horizontal slide and fade animations
* Autoplay, drag, keyboard navigation
* Arrows + bullets, fully customisable
* SEO-friendly markup with proper alt text and ARIA roles
* Lazy initialisation (the slider boots only when scrolled into view)
* Custom CSS field for fine-tuning
* Shortcode + PHP function for use in any builder (Gutenberg, Elementor, Divi, classic editor, etc.)

**Built for WordPress agencies and freelancers** by Suresh L. Bamunusinghe at [Lexiata.lk](https://lexiata.lk) — battle-tested in production sites.

= Key features =

* **Up to 8 settings tabs** — General, Size, Controls, Animations, Autoplay, Optimize, Slides, Developer
* **Image and Blank slides** — Blank slides support a custom background colour
* **Clickable URL per slide** — Set link, choose target tab, optional `nofollow`
* **Drag-to-reorder** slides in the editor
* **Duplicate slide** with one click
* **Live preview** in the slide editor
* **Click-to-copy** shortcode and PHP code
* **Fully accessible** — keyboard navigation, ARIA roles, focus rings
* **Mobile, tablet, desktop** arrow visibility controls
* **No external dependencies** — pure vanilla JS frontend (~5KB)

== Installation ==

1. Upload the `lexiata-xslider` folder to `/wp-content/plugins/` (or install directly via WordPress' Plugins screen).
2. Activate the plugin through the **Plugins** screen.
3. Click the new **XSlider** menu item in your admin sidebar.
4. Click **New project**, configure name/width/height, and click **Create project**.
5. Click **ADD SLIDE**, choose **Image** or **Blank**, configure the slide, and save.
6. Copy the shortcode (e.g. `[lex_xslider id="42"]`) and paste it into any post, page, or builder.

== Frequently Asked Questions ==

= How do I add a click-through URL to a slide? =

Open the slide editor, scroll to the **Click action** section, paste the URL, choose whether it opens in the same tab or a new tab, and toggle `nofollow` if needed.

= Where do I paste the shortcode? =

Anywhere shortcodes work: Gutenberg shortcode block, Classic Editor, Elementor shortcode widget, Divi text module, theme template files (use the PHP snippet from the General tab), etc.

= Is the plugin translation-ready? =

Yes. All strings use the `lexiata-xslider` text domain. Place `.mo` / `.po` files in `wp-content/languages/plugins/` or under the plugin's `languages/` folder.

= Does the plugin store data outside the WordPress database? =

No. Slider projects are stored as a custom post type, and slides + settings are stored in `wp_postmeta`. No external requests, no analytics, no tracking.

= How can I customise the slider visually? =

Use the **Developer** tab to add CSS classes to the wrapper or paste custom CSS that's printed inline on pages where the slider appears.

= Does it work with caching plugins? =

Yes. Frontend assets are static files with cache-friendly versioning, and the slider markup is fully server-rendered.

== Screenshots ==

1. Dashboard — list of slider projects with quick actions.
2. Slide editor — image picker, click-through URL, background colour, visibility.
3. Settings tabs — General, Size, Controls, Animations, Autoplay, Optimize, Slides, Developer.
4. Frontend — lightweight, accessible, responsive output.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Custom Post Type for slider projects with private admin-only visibility.
* 8-tab settings editor: General, Size, Controls, Animations, Autoplay, Optimize, Slides, Developer.
* 4 slide animations: Horizontal, Vertical, Fade, Zoom fade.
* 4 arrow styles: Default, Circle, Square, Minimal — with adjustable size, colour, and per-device visibility.
* 4 bullet styles: Dot, Bar, Pill, Square.
* Border-radius control (0–100px) with 5 presets — Sharp, Subtle, Soft, Rounded, Pill.
* Image and Blank slide types with click-through URLs (target tab + nofollow support).
* Touch swipe and mouse drag navigation that works across all animation types.
* Keyboard arrow navigation (activates on hover, focus, or viewport visibility).
* Drag-to-reorder slides in the admin editor with mobile touch support.
* Duplicate slide, delete slide, live preview.
* Vanilla JS frontend engine — no jQuery dependency, ~5KB minified.
* Lazy initialisation with IntersectionObserver — slider boots only when scrolled into view.
* First-slide LCP optimisation via `fetchpriority="high"`.
* Multisite-aware uninstall — cleans up all sites' data on plugin deletion.
* Accessibility: ARIA roles, focus-visible outlines, `prefers-reduced-motion` support.
* Translation-ready — all strings use the `lexiata-xslider` text domain.
* Shortcode `[lex_xslider id="X"]` works in any builder (Gutenberg, Elementor, Divi, Classic Editor, theme templates).

== Upgrade Notice ==

= 1.0.0 =
First public release.
