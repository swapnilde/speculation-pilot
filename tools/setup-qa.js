#!/usr/bin/env node
/* eslint-disable no-console */

const { execSync } = require( 'child_process' );
const path = require( 'path' );

const rootDir = path.resolve( __dirname, '..' );
const baseUrl = process.env.WP_BASE_URL || 'http://localhost:8080';
const adminUser = process.env.WP_ADMIN_USER || 'admin';
const adminPassword = process.env.WP_ADMIN_PASSWORD || 'password';
const adminEmail = process.env.WP_ADMIN_EMAIL || 'admin@example.com';

function runCmd( cmd, options = {} ) {
	try {
		return execSync( cmd, {
			cwd: rootDir,
			encoding: 'utf8',
			stdio: options.silent ? 'pipe' : 'inherit',
			...options,
		} );
	} catch ( err ) {
		if ( options.ignoreError ) {
			return err.stdout || '';
		}
		throw err;
	}
}

function wpcli( command ) {
	return runCmd( `docker compose exec -T wpcli wp ${ command } --allow-root`, { ignoreError: true, silent: true } );
}

function wpcliLive( command ) {
	return runCmd( `docker compose exec -T wpcli wp ${ command } --allow-root`, { ignoreError: true } );
}

function setup() {
	console.log( '🚀 Initializing Speculation Pilot QA Environment...' );

	// Parse port from base URL.
	const port = new URL( baseUrl ).port || '80';

	// Check for port conflicts before starting containers.
	try {
		const allContainers = runCmd(
			`docker ps --format "{{.Names}}\t{{.Ports}}"`,
			{ silent: true, ignoreError: true }
		) || '';

		const conflicting = allContainers
			.split( '\n' )
			.filter( ( line ) => line.includes( `:${ port }->` ) )
			.map( ( line ) => line.split( '\t' )[ 0 ] )
			.filter( ( name ) => ! name.startsWith( 'speculation-pilot' ) );

		if ( conflicting.length > 0 ) {
			console.error( `\n❌ Port ${ port } is already in use by: ${ conflicting.join( ', ' ) }` );
			console.error( `   Stop the conflicting container(s) first:\n` );
			conflicting.forEach( ( name ) => {
				console.error( `     docker stop ${ name }` );
			} );
			console.error( '' );
			process.exit( 1 );
		}
	} catch ( e ) {
		// Docker might not be running; let docker compose up handle it.
	}

	// Ensure Docker containers are running.
	runCmd( 'docker compose up -d' );

	// Enable Pro dev license bypass mu-plugin cleanly via base64.
	const devLicenseCode = Buffer.from( "<?php\ndefine( 'SPECULATION_PILOT_PRO_DEV_LICENSE', true );\n" ).toString( 'base64' );
	runCmd( `docker compose exec -T --user root wordpress sh -c "mkdir -p /var/www/html/wp-content/mu-plugins && echo '${ devLicenseCode }' | base64 -d > /var/www/html/wp-content/mu-plugins/sp-dev-license.php"`, { ignoreError: true, silent: true } );

	// Wait for MariaDB to become ready.
	console.log( '⏳ Waiting for database connection...' );
	let retries = 20;
	let ready = false;

	while ( retries > 0 && ! ready ) {
		try {
			const check = wpcli( 'db check' );
			if ( check && check.includes( 'Success' ) ) {
				ready = true;
				break;
			}
		} catch ( e ) {
			// Database warming up.
		}
		retries--;
		runCmd( 'node -e "setTimeout(() => {}, 2000)"', { silent: true } );
	}

	// Install WordPress Core if not already installed.
	const isInstalled = wpcli( 'core is-installed' );
	if ( ! isInstalled || ! isInstalled.includes( 'Success' ) ) {
		console.log( '📦 Installing WordPress Core...' );
		wpcliLive( `core install --url="${ baseUrl }" --title="Speculation Pilot QA" --admin_user="${ adminUser }" --admin_password="${ adminPassword }" --admin_email="${ adminEmail }" --skip-email` );
	}

	// Set pretty permalinks structure.
	wpcli( 'rewrite structure "/%postname%/" --hard' );

	// Activate Free & Pro plugins.
	console.log( '🔌 Activating plugins...' );
	wpcli( 'plugin activate speculation-pilot' );
	wpcli( 'plugin activate speculation-pilot-pro' );

	// Auto-install Pro Composer dependencies if needed.
	runCmd( `docker compose exec -T wordpress bash -c "if [ ! -d /var/www/html/wp-content/plugins/speculation-pilot-pro/vendor ] && [ -f /var/www/html/wp-content/plugins/speculation-pilot-pro/composer.json ]; then cd /var/www/html/wp-content/plugins/speculation-pilot-pro && composer install --no-dev --no-interaction --optimize-autoloader 2>/dev/null || true; fi"`, { ignoreError: true, silent: true } );

	// Enable measurement defaults in WordPress options.
	wpcli( 'eval \'$settings = \\SpeculationPilot\\Settings::defaults(); $settings["measurement_enabled"] = true; update_option( SPECULATION_PILOT_OPTION, $settings );\'' );

	// Create test pages if missing.
	const pages = [
		{ title: 'Sample Page', slug: 'sample-page' },
		{ title: 'Cart', slug: 'cart' },
		{ title: 'Checkout', slug: 'checkout' },
		{ title: 'My Account', slug: 'my-account' },
	];

	pages.forEach( ( p ) => {
		const exists = wpcli( `post list --post_type=page --name="${ p.slug }" --field=ID` );
		if ( ! exists || ! exists.trim() ) {
			wpcli( `post create --post_type=page --post_status=publish --post_title="${ p.title }" --post_name="${ p.slug }" --post_content="Speculation Pilot QA page for ${ p.title }."` );
		}
	} );

	// Run Diagnostics & Doctor check.
	console.log( '🩺 Running Speculation Pilot diagnostics...' );
	wpcliLive( 'speculation-pilot doctor' );

	console.log( `\n✅ WordPress QA site is ready!` );
	console.log( `   URL:      ${ baseUrl }` );
	console.log( `   Admin:    ${ adminUser }` );
	console.log( `   Password: ${ adminPassword }\n` );
}

setup();
