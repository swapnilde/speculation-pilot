<?php
/**
 * Activation and deactivation hooks.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot;

/**
 * Handles plugin lifecycle tasks.
 */
final class Activator {
	/**
	 * Runs on activation.
	 */
	public static function activate(): void {
		if ( false === get_option( SPECULATION_PILOT_OPTION, false ) ) {
			add_option( SPECULATION_PILOT_OPTION, Settings::defaults(), '', false );
		}

		Measurements::create_table();

		if ( ! wp_next_scheduled( SPECULATION_PILOT_CRON_CLEANUP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', SPECULATION_PILOT_CRON_CLEANUP );
		}
	}

	/**
	 * Runs on deactivation.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( SPECULATION_PILOT_CRON_CLEANUP );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, SPECULATION_PILOT_CRON_CLEANUP );
		}
	}
}
