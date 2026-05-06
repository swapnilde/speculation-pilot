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

# Activate Pro plugin if present.
if docker compose run --rm wpcli wp plugin list --field=name 2>/dev/null | grep -q 'speculation-pilot-pro'; then
	docker compose run --rm wpcli wp plugin activate speculation-pilot-pro
	# Enable dev license bypass for local testing via mu-plugin.
	docker compose exec -T wordpress bash -c "mkdir -p /var/www/html/wp-content/mu-plugins && echo \"<?php define('SPECULATION_PILOT_PRO_DEV_LICENSE', true);\" > /var/www/html/wp-content/mu-plugins/sp-dev-license.php"
	# Install Composer dependencies (DomPDF) if composer.json exists.
	if docker compose exec -T wordpress test -f /var/www/html/wp-content/plugins/speculation-pilot-pro/composer.json; then
		echo "Installing Pro plugin Composer dependencies..."
		docker compose exec -T wordpress bash -c "cd /var/www/html/wp-content/plugins/speculation-pilot-pro && composer install --no-dev --no-interaction --optimize-autoloader 2>/dev/null || echo 'Composer not available in container — run manually.'"
	fi
fi

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

