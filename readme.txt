=== Debloat - Remove Unused CSS, Optimize JS ===
Contributors: asadkn
Tags: speed, performance, uncss, optimize
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.1
Stable tag: 1.2.8
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Remove Unused CSS, Optimize CSS, Optimize JS and speed up your site.

== Description ==
A plugin for Advanced Users to Optimize CSS Delivery, Remove Unused CSS, Optimize Javascript Delivery with defer or delay load JS. 
The perfect toolkit for improving your Core Web Vitals and overall website performance.

WARNING: It's a powerful speed optimization plugin that's meant for power users who know what they're doing. 

**Features:**

* Optimize CSS: Fix Render-Blocking.
* Minify and Inline CSS.
* Remove Unused CSS (Advanced).
* Optimize JS: Fix Render-Blocking with defer.
* Delay Load some JS until user interaction.
* Adds resource hints for faster Google Fonts.
* Built-in optimizations for Elementor (free version).
* Built-in optimizations for WPBakery Page Builder. 
* Compatible with all themes and plugins.
* Supports all modern browsers (no IE11 support).
* Optimized code benchmarked for performance.
* Built-in cache for processing.
* Compatible with cache plugins (disable their JS and CSS optimizations).
* API and hooks for theme & plugin authors.

== Installation ==

1. Upload/Install and activate the plugin.
2. Go to *Settings* > Debloat Optimize, and configure per your requirement.
3. Clear all caches from any cache plugin you may have active.

== Changelog ==

= 1.2.8 =
* Added: Support for a new lazyload from ThemeSphere.
* Fixed: A harmless PHP Warning.

= 1.2.7 =
* Improved: Elementor integration delay load rules are relaxed to exclude the new lazyload.

= 1.2.4 =
* Fixed: Handling of empty or malformed script tags.
* Fixed: WPBakery and WooCommerce conflict with delay JS on flexslider.
* Fixed: A rare PHP warning on clearing the cache, including from cache plugins.

= 1.2.3 =
* Added: Support for media queries when unused CSS is removed.
* Fixed: PHP warnings on PHP 8.2.
* Fixed: Parsing complex CSS selectors with commas and several other complex rules.
* Updated: Sabberworm CSS Parser to 8.4 with patches.

= 1.2.1 =
* Fixed: A few minor PHP warnings / notices.
* Improved: Elementor admin bar drop downs should render in delay JS.

= 1.2.0 =
* Fixed: Error when malformed link tags with missing href.

= 1.1.9 =
* Fixed: Google Fonts inline feature when additional parameters present in URL.
* Improved: Strip unnecessary rel=stylesheet in inline styles.

= 1.1.7 =
* Added: Option to defer inline scripts - useful if some dependent inline scripts not registered using WordPress enqueues.
* Fixed: Defer inline scripts with jQuery if jquery-core is deferred. 

= 1.1.6 =
* Fixed: Delay/defer only replacing one instance with duplicated <script> tags of the same URL and id.

= 1.1.5 =
* Added: New filter debloat/should_process.
* Improved: Skip converting already qualified URLs.
* Bumped required PHP version to 7.1+.

= 1.1.4 =
* Fixed: Some PHP notices under certain conditions.

= 1.1.3 =
* Added: Maximum user interaction wait time for JS delay load feature.

= 1.1.2 =
* Fixed: Disable processing in feeds and on missing HTML tag.

= 1.1.1 =
* Fixed: Fatal error for Sabberworm lib and resolved/matched AMP plugin dependency.

= 1.1.0 =
* Added: WP CLI Commands to empty caches.
* Added: Google Fonts optimizations such as font-display: swap.
* Added: Option to inline Google Fonts CSS.

= 1.0.0 =
* Initial release.