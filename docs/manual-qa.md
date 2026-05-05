# Speculation Pilot Manual QA

## Environment

- WordPress 6.8 or newer.
- Pretty permalinks enabled.
- Chrome stable.
- One logged-out browser session or incognito window.
- Optional: WooCommerce active with cart, checkout, my-account, and product pages.

## Disposable Local Site

- Start Docker Desktop.
- Run `npm run qa:up`.
- Run `npm run qa:setup`.
- Open `http://localhost:8080`.
- Log in with `admin` / `password`.

## Activation

- Activate the plugin.
- Confirm no fatal error.
- Confirm **Settings > Speculation Pilot** loads.
- Confirm defaults are enabled, prefetch, conservative, safe preset.

## Core Rules

- Visit a logged-out frontend page.
- Open Chrome DevTools > Application > Background Services > Speculative loads.
- Confirm speculation rules are present.
- Switch to disabled mode and save.
- Confirm Core rules are no longer modified by the plugin.

## Exclusions

- Enable WooCommerce preset.
- Confirm effective exclusions include cart, checkout, my-account, order-pay, order-received, add-to-cart, and wc-ajax patterns.
- Add a manual exclusion such as `/demo-private/*`.
- Save and confirm it appears in the effective preview.

## Prerender

- Apply Aggressive Lab.
- Confirm warning appears in the Rules screen.
- Test in a logged-out session.
- Confirm cart, checkout, account, and payment paths remain excluded.

## Measurements

- Enable local measurement.
- Visit several frontend pages as logged-out user.
- Return to wp-admin and refresh Measurements.
- Confirm sample count increases.
- Confirm paths do not include query strings or fragments.
- Confirm no IP, user ID, user agent, email, or full URL fields exist in `wp_speculation_pilot_events`.

## Site Health

- Open Tools > Site Health.
- Confirm the Speculation Pilot readiness test appears.
- Confirm warnings match the plugin Diagnostics screen.

## WP-CLI

- Run `wp speculation-pilot settings`.
- Run `wp speculation-pilot doctor`.
- Run `wp speculation-pilot exclusions`.
- Run `wp speculation-pilot report`.
- Run `wp speculation-pilot doctor --format=json` and confirm valid JSON output.

## Uninstall

- Disable **Delete plugin data on uninstall**.
- Uninstall plugin and confirm data remains.
- Reinstall, enable cleanup, uninstall again, and confirm option/table cleanup.
