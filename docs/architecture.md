# Speculation Pilot Architecture

## Runtime

Speculation Pilot is a standalone WordPress plugin. It does not depend on a SaaS service.

- Main file: `speculation-pilot.php`
- PHP namespace: `SpeculationPilot`
- Settings option: `speculation_pilot_settings`
- Measurement table: `wp_speculation_pilot_events`
- REST namespace: `/wp-json/speculation-pilot/v1`

## Services

- `Plugin` wires all services together.
- `Settings` owns defaults, validation, and persistence.
- `SafetyEngine` builds path exclusions from presets, integrations, and manual paths.
- `CoreIntegration` connects settings to WordPress Core speculative loading filters.
- `Diagnostics` reports compatibility and safety state for wp-admin.
- `Measurements` stores and aggregates privacy-safe local timing events.
- `RestApi` exposes admin and anonymous measurement endpoints.
- `Admin` mounts the React wp-admin app.
- `Frontend` enqueues the measurement script when explicitly enabled.
- `SiteHealth` adds a readiness check to Tools > Site Health.
- `Cli` exposes developer-friendly `wp speculation-pilot` commands.

## Core Integration

The plugin uses public WordPress 6.8+ APIs:

- `wp_speculation_rules_configuration`
- `wp_speculation_rules_href_exclude_paths`

It preserves Core behavior when disabled or set to Core default. It does not call private Core functions directly.

## Privacy

Measurement is disabled by default. When enabled, the frontend script sends local timing data to the site itself.

Stored fields:

- current local path
- previous same-origin local path
- navigation type
- duration, TTFB, DOM interactive, load complete
- activation start and prerender flag
- active mode and eagerness
- timestamp

Never stored:

- IP address
- user ID
- cookies
- query strings
- fragments
- form values
- email addresses
- full URLs
- user agents

## Reporting

The local report API returns:

- p50 and p75 duration and TTFB
- top paths
- grouped path summaries
- daily p75 trend data
- mode/eagerness breakdowns

Admins can export path data to CSV or clear local measurement rows from the Measurements screen.

## Build Assets

The build uses a deterministic copy script:

```bash
npm run build
```

This copies source files into `build/` and writes WordPress asset metadata. The checked-in `build/` directory makes the plugin immediately installable.

## Operations

WP-CLI commands:

```bash
wp speculation-pilot settings
wp speculation-pilot doctor
wp speculation-pilot exclusions
wp speculation-pilot report
```

The `doctor` command supports `--format=json` and `--fail-on-warning` for CI workflows.

## QA Tooling

- `docker-compose.yml` runs a disposable WordPress 6.8 + MariaDB site.
- `tools/setup-qa.js` installs WordPress cross-platform, activates plugins, enables measurement, creates QA pages, and runs `wp speculation-pilot doctor`.
- `tests/e2e` contains Playwright checks for the admin app and logged-out frontend behavior.
- `tools/smoke-test.js` verifies required files, synced build assets, package versions, PNG assets, and privacy-sensitive frontend tokens.
