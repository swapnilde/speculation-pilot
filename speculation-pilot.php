<?php
/**
 * Plugin Name: Speculation Pilot
 * Plugin URI:  https://speculationpilot.com
 * Description: Safely configure, diagnose, and measure WordPress speculative loading.
 * Version:     0.3.0
 * Author:      Speculation Pilot
 * Author URI:  https://speculationpilot.com
 * Text Domain: speculation-pilot
 * Requires at least: 6.8
 * Requires PHP: 7.4
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPECULATION_PILOT_VERSION', '0.3.0' );
define( 'SPECULATION_PILOT_DB_VERSION', '1' );
define( 'SPECULATION_PILOT_MIN_WP', '6.8' );
define( 'SPECULATION_PILOT_MIN_PHP', '7.4' );
define( 'SPECULATION_PILOT_FILE', __FILE__ );
define( 'SPECULATION_PILOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPECULATION_PILOT_URL', plugin_dir_url( __FILE__ ) );
define( 'SPECULATION_PILOT_OPTION', 'speculation_pilot_settings' );
define( 'SPECULATION_PILOT_CRON_CLEANUP', 'speculation_pilot_cleanup_measurements' );
define( 'SPECULATION_PILOT_FREE_RETENTION_DAYS', 7 );
define( 'SPECULATION_PILOT_FREE_MAX_EXCLUSIONS', 5 );
define( 'SPECULATION_PILOT_FREE_MAX_TOP_PATHS', 3 );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'SpeculationPilot\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$relative = str_replace( '\\', '/', $relative );
		$file     = SPECULATION_PILOT_PATH . 'includes/' . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( SpeculationPilot\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( SpeculationPilot\Activator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		SpeculationPilot\Plugin::instance()->boot();
	}
);

