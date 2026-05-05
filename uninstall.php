<?php
/**
 * Uninstall cleanup.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'speculation_pilot_settings', array() );
$cleanup  = is_array( $settings ) && ! empty( $settings['cleanup_on_uninstall'] );

$timestamp = wp_next_scheduled( 'speculation_pilot_cleanup_measurements' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'speculation_pilot_cleanup_measurements' );
}

// Allow the Pro plugin to clean up its own data.
do_action( 'speculation_pilot_uninstall', $cleanup );

if ( ! $cleanup ) {
	return;
}

global $wpdb;

delete_option( 'speculation_pilot_settings' );
delete_option( 'speculation_pilot_db_version' );
delete_option( 'speculation_pilot_license' );

$table_name = $wpdb->prefix . 'speculation_pilot_events';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

