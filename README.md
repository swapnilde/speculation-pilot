# Speculation Pilot

Speculation Pilot is a WordPress plugin for safely configuring, diagnosing, and measuring speculative loading on WordPress 6.8+ sites.

It wraps WordPress Core's speculative loading API with practical presets, risky-route exclusions, WooCommerce-aware defaults, diagnostics, and privacy-safe local timing measurements.

## Features

- Configure Core speculative loading mode: Core default, disabled, prefetch, or prerender.
- Configure eagerness: Core default, conservative, moderate, or eager.
- Use safe, balanced, and aggressive lab presets.
- Add path exclusions for carts, checkout, accounts, payment callbacks, search, query-string actions, and custom routes.
- Detect and protect common WooCommerce and EDD flows.
- Show diagnostics for WordPress version, PHP version, pretty permalinks, Core hook availability, and prerender safety.
- Review readiness from WordPress Site Health.
- Use WP-CLI commands for settings, diagnostics, exclusions, and local reports.
- Record anonymous local navigation timing measurements when explicitly enabled.
- Store no IP addresses, cookies, user IDs, query strings, form values, emails, full URLs, or user agents in measurement events.

## Requirements

- WordPress 6.8 or newer.
- PHP 7.4 or newer.
- Pretty permalinks for Core speculative loading behavior.

## Installation

1. Place this directory in `wp-content/plugins/speculation-pilot`.
2. Activate **Speculation Pilot** in wp-admin.
3. Open **Settings > Speculation Pilot**.
4. Start with **Safe Boost**, save settings, and inspect the Diagnostics screen.

## Development

Install JavaScript dependencies:

```bash
npm install
```

Install PHP development dependencies:

```bash
composer install
```

Run checks:

```bash
npm test
npm run lint:js
npm run lint:css
composer phpcs
composer phpunit
```

## WP-CLI

```bash
wp speculation-pilot settings
wp speculation-pilot doctor
wp speculation-pilot exclusions
wp speculation-pilot report
```

Build assets:

```bash
npm run build
```

Package a release ZIP:

```bash
npm run plugin-zip
```

## Local WordPress QA

Start a disposable WordPress 6.8 test site with Docker:

```bash
cp .env.example .env
npm run qa:up
npm run qa:setup
```

Then open `http://localhost:8080` and log in with `admin` / `password`.

Run browser tests after installing Node dependencies and Playwright browsers:

```bash
npm run test:e2e
```

## Privacy

Measurement is disabled by default. When enabled, Speculation Pilot stores only local paths and timing metrics in a local WordPress database table. It strips query strings and fragments before storage.

## Current Version

0.3.0 beta.
