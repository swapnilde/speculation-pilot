=== Speculation Pilot ===
Contributors: speculationpilot
Tags: performance, speculative-loading, prefetch, prerender, core-web-vitals
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safely configure, diagnose, and measure WordPress speculative loading.

== Description ==

Speculation Pilot helps WordPress developers and agencies safely tune the speculative loading feature introduced in WordPress 6.8.

The plugin adds practical presets, route exclusions, WooCommerce-aware safeguards, diagnostics, and optional privacy-safe local performance measurements.

= Highlights =

* Configure prefetch or prerender behavior.
* Choose conservative, moderate, or eager loading.
* Start with Safe Boost, Balanced, or Aggressive Lab presets.
* Exclude risky paths such as cart, checkout, account, payment, search, callbacks, and custom routes.
* Protect WooCommerce and Easy Digital Downloads flows.
* Protect membership, LMS, multilingual, cache-bypass, and funnel checkout flows.
* Diagnose inactive speculative loading states.
* Review readiness from WordPress Site Health.
* Use WP-CLI commands for settings, diagnostics, exclusions, and reports.
* Measure local navigation timing with p75 trends, path grouping, mode breakdowns, and CSV export.

== Screenshots ==

1. Overview dashboard with mode, diagnostics, exclusions, and timing metrics.
2. Rules and exclusions for WordPress commerce and content sites.
3. Privacy-safe measurement reporting with trend and path-group views.

== Installation ==

1. Upload the `speculation-pilot` directory to `/wp-content/plugins/`.
2. Activate Speculation Pilot from the Plugins screen.
3. Open Settings > Speculation Pilot.
4. Start with Safe Boost and inspect Diagnostics.

== Frequently Asked Questions ==

= Does this replace WordPress Core speculative loading? =

No. Speculation Pilot configures and extends Core behavior through public WordPress hooks.

= Is measurement enabled by default? =

No. Measurement is off until an administrator enables it.

= What data does measurement store? =

Only local paths and timing numbers. It strips query strings and fragments, and does not store IP addresses, cookies, user IDs, form values, emails, full URLs, or user agents.

= Is prerender safe for WooCommerce? =

Prerender needs care because page JavaScript can run before a visitor completes navigation. Speculation Pilot keeps cart, checkout, account, and payment routes excluded by default.

== Changelog ==

= 0.3.0 =

* Added WordPress Site Health readiness check.
* Added WP-CLI commands for settings, diagnostics, exclusions, and measurement reports.
* Added richer reporting with daily trends, path groups, mode breakdowns, CSV export, and clear action.
* Added membership, LMS, multilingual, cache-bypass, and funnel checkout compatibility presets.
* Added WordPress.org banner, icon, screenshot, and translation template assets.
* Added Docker and Playwright QA scaffolding.
* Hardened anonymous measurement endpoint with payload-size, malformed JSON, and unexpected-field validation.
* Added link opt-out selector preview in Diagnostics.
* Added no-dependency smoke test and additional test coverage.

= 0.1.0 =

* Initial beta release.
* Added Core speculative loading configuration controls.
* Added safety presets and path exclusions.
* Added admin UI, diagnostics, REST API, measurement storage, and frontend timing script.
