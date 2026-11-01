=== DEV3 AI Mega Menu for WordPress ===
Contributors: dev3tech
Tags: mega menu, menu, navigation, accessibility, ai
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accessible mega menus with columns, tabs, flyouts, products, posts and promos. DEV3 AI builds, labels and audits your menu.

== Description ==

DEV3 AI Mega Menu turns any WordPress menu into a fast, accessible mega menu, in classic and block themes.

**Menus and panels**

* Mega panels with automatic columns, or custom rows and columns on a 12-column grid
* Tabbed mega panels, flyout dropdowns at any depth, image and icon items
* Panel widgets: menu columns, recent posts, WooCommerce products, search box, image, promo block, HTML, shortcode and synced patterns (reusable blocks)
* Badges ("New", "Sale", "Hot" or a custom colour), 60 built-in line icons, item images and descriptions
* Hover or click triggers, sticky bar, accordion or off-canvas mobile menu, configurable breakpoint
* Style presets plus a visual style editor with a live preview
* Keyboard and screen-reader accessible (WAI disclosure pattern, ARIA tabs, focus trap, Escape, arrow keys; WCAG 2.2 AA)
* Classic themes (theme locations), block themes (block, or automatic replacement of the Navigation block), shortcode, Elementor widget and WPBakery element
* Front-end: about 3 KB of JavaScript and 8 KB of CSS (gzipped), no dependencies, no AI calls on page load

**DEV3 AI features (your own Google Gemini API key)**

* Build my menu: reads your pages, categories and products and proposes a complete mega menu
* Labels and short descriptions for every item, in your site's language
* Menu UX audit: too deep, crowded, unclear labels, broken and dead-end links, missing pages, with one-click fixes
* AI promo panel: a seasonal promo block with featured products
* Icon and image suggestions for each item

Every AI result is a draft. Nothing changes on your site until you accept it. Everything except the AI tools works without an API key, and an automatic builder, a quick audit, keyword icon matching and promo templates work without AI.

== Installation ==

1. Upload the plugin zip under Plugins > Add New > Upload Plugin and activate it.
2. Open DEV3 AI Mega Menu in the admin menu.
3. Build a menu (Menu Builder or AI Studio > Build my menu).
4. Classic themes: Locations > turn on "Use the DEV3 AI Mega Menu here". Block themes: add the "DEV3 AI Mega Menu" block to your header, or set "Replace the Navigation block".
5. Optional: add your Google Gemini API key under API Config.

== Frequently Asked Questions ==

= Does it work without an API key? =
Yes. Menus, panels, widgets, styles, locations, the automatic builder and the quick audit all work without AI.

= Does AI run when visitors load a page? =
No. AI only runs when an administrator clicks a DEV3 AI button. The front end makes no AI calls.

= Which page builders are supported? =
Gutenberg (block), Elementor (widget), WPBakery (element), and any builder that runs shortcodes: [dev3mm_menu menu="main-menu"].

== Privacy ==

The AI tools send menu labels, page/category/product titles and IDs to Google Gemini. No visitor data is sent. See the HTML documentation for details.

== Screenshots ==

1. Mega panel with columns and an AI promo block
2. Tabbed mega panel
3. Off-canvas mobile menu
4. Menu Builder with panel editor
5. Menu UX audit with one-click fixes
6. Visual style editor

== Changelog ==

= 1.0.0 =
* Initial release.
