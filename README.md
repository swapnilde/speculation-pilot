# Speculation Pilot

![Version](https://img.shields.io/badge/version-0.3.0-blue.svg) ![WordPress](https://img.shields.io/badge/WordPress-6.8%2B-21759b.svg) ![Tested up to](https://img.shields.io/badge/tested%20up%20to-WP%207.0-21759b.svg) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg) ![License](https://img.shields.io/badge/license-GPLv2%20or%20later-green.svg) [![Donate](https://img.shields.io/badge/Donate-PayPal-00457C.svg)](https://paypal.me/SwapnilDeshpandeIN)

**Contributors:** [@swapnilde](https://profiles.wordpress.org/swapnilde/)

> Safely configure, diagnose, and measure WordPress speculative loading with safety presets, route exclusions, and privacy-friendly timing.

## Description

Speculation Pilot helps WordPress site owners, developers, and agencies safely configure, tune, and measure the Speculation Rules API and speculative loading features introduced in WordPress 6.8.

Speculative loading can dramatically accelerate perceived page transitions by prefetching or prerendering links in the background before a visitor clicks. However, aggressive prerendering on dynamic sites can trigger unwanted server load, interfere with cart sessions, or fire unwanted analytics triggers. Speculation Pilot gives you complete, safe, and granular control.

### Key Features

* **Speculation Mode Control**: Seamlessly toggle between Prefetch (conservative memory & network usage) and Prerender (near-instant page loads).
* **Eagerness Levels**: Set speculation timing to Conservative (on click / mouse down), Moderate (on hover / pointer over), or Eager (immediate when in viewport).
* **One-Click Safety Presets**:
  * *Safe Boost*: Conservative prefetching with full eCommerce protections enabled.
  * *Balanced*: Moderate prefetching on hover for standard content and blog pages.
  * *Aggressive Lab*: Prerendering with selective exclusions for staging or controlled environments.
* **Smart Route Exclusions**: Easily exclude sensitive or dynamic URL paths like carts, checkouts, account pages, search queries (`?s=`), nonces, and custom endpoints.
* **Commerce & Membership Protection**: Built-in compatibility safeguards for WooCommerce, Easy Digital Downloads (EDD), MemberPress, LearnDash, and major LMS/membership plugins.
* **Link Opt-Out Selectors**: Define CSS selectors (such as `.no-prerender`, `[data-no-speculation]`) to prevent specific links from being speculated.
* **Built-in Diagnostics & Site Health**: Instantly diagnose why speculation might be inactive or throttled, integrated directly with the WordPress Site Health screen.
* **Privacy-Safe Local Measurement**: Optionally collect aggregated, anonymized navigation timing metrics (p75 duration, path groups, mode breakdown) stored entirely in your local database with automatic 7-day cleanup. Zero external requests, zero cookies, zero PII.
* **WP-CLI Support**: Manage configuration, view diagnostics, list exclusions, and generate performance reports directly from your terminal.

### Why Speculation Pilot?

While WordPress Core provides underlying speculative loading support, site administrators often need safety rails to prevent prerendering administrative or transactional pages. Speculation Pilot provides a clean dashboard, automated safeguards, and developer tools to achieve maximum speed with zero breakage.

## Installation

### From the WordPress Dashboard

1. Navigate to **Plugins > Add New**.
2. Search for `Speculation Pilot`.
3. Click **Install Now** and then **Activate**.
4. Go to **Settings > Speculation Pilot** to choose your preset.

### Manual Installation

1. Download the plugin ZIP archive.
2. Upload the unzipped `speculation-pilot` folder to your `/wp-content/plugins/` directory.
3. Activate the plugin through the **Plugins** menu in WordPress.
4. Access settings via **Settings > Speculation Pilot**.

### Quick Setup Recommendation

1. Choose the **Safe Boost** preset to start.
2. Verify that your cart and checkout URLs appear in the **Route Exclusions** list.
3. Review the **Diagnostics** panel to confirm browser support and speculation rule delivery.

## Frequently Asked Questions

### Does this replace the speculative loading feature in WordPress Core?

No. Speculation Pilot configures and enhances WordPress Core's speculative loading API (`wp_speculation_rules`) using official hooks and filters.

### What is the difference between Prefetch and Prerender?

* **Prefetch** downloads the HTML document in the background so it is cached and ready in memory when clicked. It is lightweight and safe for almost all pages.
* **Prerender** downloads and completely renders the page in a background process, including running HTML and CSS. It offers near-instant loading times, but requires careful exclusions for stateful or transaction-heavy pages.

### Is speculative loading safe for WooCommerce and eCommerce sites?

Yes, when properly configured. Speculation Pilot automatically excludes WooCommerce cart (`/cart/`), checkout (`/checkout/`), customer account (`/my-account/`), and checkout endpoint URLs to ensure customer carts and orders are never unintentionally modified or duplicated in the background.

### Is performance measurement enabled by default?

No. Performance measurement is completely optional and disabled by default. You can enable it in the plugin settings if you want to measure real-world navigation timings locally.

### What data is collected if measurement is enabled?

Only anonymous local navigation duration and sanitized relative URL paths. Speculation Pilot:
* Does NOT track IP addresses or user agents.
* Does NOT use cookies or local storage trackers.
* Does NOT send any data to external servers or third parties.
* Automatically purges data older than 7 days.

### Can I exclude specific links using CSS classes?

Yes. You can configure custom opt-out CSS selectors (such as `.no-speculate` or `[data-no-prerender]`) in the settings. Any link matching these selectors will be ignored by the browser speculation engine.

### Does Speculation Pilot support WP-CLI?

Yes. Speculation Pilot includes full WP-CLI command sets:
* `wp speculation-pilot status` — View active configuration and diagnostics.
* `wp speculation-pilot get <key>` — Read setting values.
* `wp speculation-pilot set <key> <value>` — Update settings.
* `wp speculation-pilot report` — View aggregated performance metrics in the CLI.

## Screenshots

1. Main Dashboard: Speculation mode controls, eagerness settings, and one-click safety presets.
2. Exclusions & Rules: Granular route exclusion list and commerce compatibility toggles.
3. Performance Insights: Local navigation timing trends, p75 durations, and route group analysis.

## Changelog

### 0.3.0

* Added WordPress Site Health readiness check integration.
* Added WP-CLI commands for settings, diagnostics, exclusions, and performance reporting.
* Added richer performance analytics with daily trends, path grouping, mode breakdowns, and CSV export.
* Added compatibility presets for WooCommerce, EDD, membership, LMS, and funnel checkouts.
* Added link opt-out CSS selector configuration and Diagnostics preview.
* Hardened anonymous local measurement REST endpoint with payload validation and strict type checks.
* Added complete translation templates and internationalization support.
* Added official WordPress.org banners, icons, and screenshot assets.

### 0.1.0

* Initial public release.
* Core speculative loading configuration controls (mode and eagerness).
* Safety presets (Safe Boost, Balanced, Aggressive Lab).
* Dynamic route exclusions and URL path filtering.
* Admin settings interface and diagnostic health checks.
* Optional local privacy-safe navigation measurement.

## Upgrade Notice

### 0.3.0

Version 0.3.0 introduces WordPress Site Health checks, WP-CLI tooling, enhanced route presets for WooCommerce and membership sites, and performance analytics. Recommended for all users.
