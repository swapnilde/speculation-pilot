#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${WP_BASE_URL:-http://localhost:8080}"
ADMIN_USER="${WP_ADMIN_USER:-admin}"
ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-password}"
ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"

docker compose up -d db wordpress

echo "Waiting for WordPress database..."
until docker compose run --rm wpcli wp db check >/dev/null 2>&1; do
	sleep 3
done

if ! docker compose run --rm wpcli wp core is-installed >/dev/null 2>&1; then
	docker compose run --rm wpcli wp core install \
		--url="${BASE_URL}" \
		--title="Speculation Pilot QA" \
		--admin_user="${ADMIN_USER}" \
		--admin_password="${ADMIN_PASSWORD}" \
		--admin_email="${ADMIN_EMAIL}" \
		--skip-email
fi

docker compose run --rm wpcli wp rewrite structure '/%postname%/' --hard
docker compose run --rm wpcli wp plugin activate speculation-pilot

docker compose run --rm wpcli wp eval '
$settings = \SpeculationPilot\Settings::defaults();
$settings["measurement_enabled"] = true;
update_option( SPECULATION_PILOT_OPTION, $settings );
'

create_page() {
	local title="$1"
	local slug="$2"
	if ! docker compose run --rm wpcli wp post list --post_type=page --name="${slug}" --field=ID | grep -q .; then
		docker compose run --rm wpcli wp post create \
			--post_type=page \
			--post_status=publish \
			--post_title="${title}" \
			--post_name="${slug}" \
			--post_content="Speculation Pilot QA page for ${title}."
	fi
}

create_page "Sample Page" "sample-page"
create_page "Cart" "cart"
create_page "Checkout" "checkout"
create_page "My Account" "my-account"

docker compose run --rm wpcli wp speculation-pilot doctor

echo "WordPress QA site ready: ${BASE_URL}"

