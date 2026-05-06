<?php
/**
 * Measurement storage and reporting.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot;

/**
 * Handles privacy-safe local performance measurements.
 */
final class Measurements {
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( SPECULATION_PILOT_CRON_CLEANUP, array( $this, 'cleanup' ) );

		if ( SPECULATION_PILOT_DB_VERSION !== (string) get_option( 'speculation_pilot_db_version', '' ) ) {
			self::create_table();
		}
	}

	/**
	 * Creates or updates the custom table.
	 */
	public static function create_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			current_path varchar(255) NOT NULL,
			previous_path varchar(255) DEFAULT '',
			navigation_type varchar(32) DEFAULT '',
			duration decimal(10,2) DEFAULT NULL,
			ttfb decimal(10,2) DEFAULT NULL,
			dom_interactive decimal(10,2) DEFAULT NULL,
			load_complete decimal(10,2) DEFAULT NULL,
			activation_start decimal(10,2) DEFAULT NULL,
			was_prerender tinyint(1) DEFAULT 0,
			mode varchar(20) DEFAULT '',
			eagerness varchar(20) DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY current_path (current_path(191))
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( 'speculation_pilot_db_version', SPECULATION_PILOT_DB_VERSION, false );
	}

	/**
	 * Returns the custom table name.
	 */
	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'speculation_pilot_events';
	}

	/**
	 * Stores a measurement payload.
	 *
	 * @param array<string, mixed> $payload Raw payload.
	 * @return array<string, mixed>
	 */
	public function record( array $payload ): array {
		global $wpdb;

		$settings = $this->settings->get();

		if ( empty( $settings['measurement_enabled'] ) ) {
			return array(
				'accepted' => false,
				'reason'   => 'measurement_disabled',
			);
		}

		if ( ! self::table_exists() ) {
			self::create_table();
		}

		if ( ! $this->passes_rate_limit() ) {
			return array(
				'accepted' => false,
				'reason'   => 'rate_limited',
			);
		}

		$event = $this->sanitize_event( $payload );

		if ( is_wp_error( $event ) ) {
			return array(
				'accepted' => false,
				'reason'   => $event->get_error_code(),
			);
		}

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'current_path'     => $event['current_path'],
				'previous_path'    => $event['previous_path'],
				'navigation_type'  => $event['navigation_type'],
				'duration'         => $event['duration'],
				'ttfb'             => $event['ttfb'],
				'dom_interactive'  => $event['dom_interactive'],
				'load_complete'    => $event['load_complete'],
				'activation_start' => $event['activation_start'],
				'was_prerender'    => $event['was_prerender'],
				'mode'             => $event['mode'],
				'eagerness'        => $event['eagerness'],
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return array(
				'accepted' => false,
				'reason'   => 'db_insert_failed',
			);
		}

		return array(
			'accepted' => true,
		);
	}

	/**
	 * Returns aggregated report data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_report(): array {
		global $wpdb;

		$settings       = $this->settings->get();
		$requested_days = max( 1, (int) $settings['retention_days'] );
		$days           = Settings::get_effective_retention_days( $requested_days );
		$table          = self::table_name();
		$since          = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $days ) );
		$is_pro         = (bool) apply_filters( 'speculation_pilot_is_pro', false );
		$max_top_paths  = (int) apply_filters( 'speculation_pilot_max_top_paths', SPECULATION_PILOT_FREE_MAX_TOP_PATHS );

		if ( ! self::table_exists() ) {
			return $this->empty_report( $settings );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT current_path, duration, ttfb, load_complete, was_prerender, mode, eagerness, created_at
				FROM {$table}
				WHERE created_at >= %s
				ORDER BY created_at DESC
				LIMIT 5000",
				$since
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$durations = array();
		$ttfbs     = array();
		$paths     = array();
		$daily     = array();
		$groups    = array();
		$modes     = array();

		foreach ( $rows as $row ) {
			if ( null !== $row['duration'] && '' !== $row['duration'] ) {
				$durations[] = (float) $row['duration'];
			}

			if ( null !== $row['ttfb'] && '' !== $row['ttfb'] ) {
				$ttfbs[] = (float) $row['ttfb'];
			}

			$path = (string) $row['current_path'];
			$date = substr( (string) $row['created_at'], 0, 10 );
			$group_label = self::path_group( $path );
			$mode_key    = trim( (string) $row['mode'] . ' / ' . (string) $row['eagerness'], ' /' );

			if ( '' === $mode_key ) {
				$mode_key = __( 'Unknown', 'speculation-pilot' );
			}

			if ( ! isset( $paths[ $path ] ) ) {
				$paths[ $path ] = array(
					'path'        => $path,
					'samples'     => 0,
					'durations'   => array(),
					'prerenders'  => 0,
					'last_sample' => $row['created_at'],
				);
			}

			++$paths[ $path ]['samples'];
			if ( null !== $row['duration'] && '' !== $row['duration'] ) {
				$paths[ $path ]['durations'][] = (float) $row['duration'];
			}
			if ( ! empty( $row['was_prerender'] ) ) {
				++$paths[ $path ]['prerenders'];
			}

			if ( ! isset( $daily[ $date ] ) ) {
				$daily[ $date ] = array(
					'date'       => $date,
					'samples'    => 0,
					'durations'  => array(),
					'ttfbs'      => array(),
					'prerenders' => 0,
				);
			}

			++$daily[ $date ]['samples'];
			if ( null !== $row['duration'] && '' !== $row['duration'] ) {
				$daily[ $date ]['durations'][] = (float) $row['duration'];
			}
			if ( null !== $row['ttfb'] && '' !== $row['ttfb'] ) {
				$daily[ $date ]['ttfbs'][] = (float) $row['ttfb'];
			}
			if ( ! empty( $row['was_prerender'] ) ) {
				++$daily[ $date ]['prerenders'];
			}

			if ( ! isset( $groups[ $group_label ] ) ) {
				$groups[ $group_label ] = array(
					'group'      => $group_label,
					'samples'    => 0,
					'durations'  => array(),
					'prerenders' => 0,
				);
			}

			++$groups[ $group_label ]['samples'];
			if ( null !== $row['duration'] && '' !== $row['duration'] ) {
				$groups[ $group_label ]['durations'][] = (float) $row['duration'];
			}
			if ( ! empty( $row['was_prerender'] ) ) {
				++$groups[ $group_label ]['prerenders'];
			}

			if ( ! isset( $modes[ $mode_key ] ) ) {
				$modes[ $mode_key ] = array(
					'mode'      => $mode_key,
					'samples'   => 0,
					'durations' => array(),
				);
			}

			++$modes[ $mode_key ]['samples'];
			if ( null !== $row['duration'] && '' !== $row['duration'] ) {
				$modes[ $mode_key ]['durations'][] = (float) $row['duration'];
			}
		}

		$top_paths = array_values(
			array_map(
				static function ( array $path_data ): array {
					return array(
						'path'        => $path_data['path'],
						'samples'     => $path_data['samples'],
						'p75Duration' => self::percentile( $path_data['durations'], 75 ),
						'prerenders'  => $path_data['prerenders'],
						'lastSample'  => $path_data['last_sample'],
					);
				},
				$paths
			)
		);

		usort(
			$top_paths,
			static function ( array $a, array $b ): int {
				return $b['samples'] <=> $a['samples'];
			}
		);

		$daily_series = array_values(
			array_map(
				static function ( array $day ): array {
					return array(
						'date'        => $day['date'],
						'samples'     => $day['samples'],
						'p75Duration' => self::percentile( $day['durations'], 75 ),
						'p75Ttfb'     => self::percentile( $day['ttfbs'], 75 ),
						'prerenders'  => $day['prerenders'],
					);
				},
				$daily
			)
		);

		usort(
			$daily_series,
			static function ( array $a, array $b ): int {
				return strcmp( $a['date'], $b['date'] );
			}
		);

		$path_groups = array_values(
			array_map(
				static function ( array $group ): array {
					return array(
						'group'       => $group['group'],
						'samples'     => $group['samples'],
						'p75Duration' => self::percentile( $group['durations'], 75 ),
						'prerenders'  => $group['prerenders'],
					);
				},
				$groups
			)
		);

		usort(
			$path_groups,
			static function ( array $a, array $b ): int {
				return $b['samples'] <=> $a['samples'];
			}
		);

		$mode_breakdown = array_values(
			array_map(
				static function ( array $mode ): array {
					return array(
						'mode'        => $mode['mode'],
						'samples'     => $mode['samples'],
						'p75Duration' => self::percentile( $mode['durations'], 75 ),
					);
				},
				$modes
			)
		);

		return array(
			'enabled'        => (bool) $settings['measurement_enabled'],
			'retentionDays'  => $days,
			'sampleCount'    => count( $rows ),
			'p50Duration'    => self::percentile( $durations, 50 ),
			'p75Duration'    => self::percentile( $durations, 75 ),
			'p50Ttfb'        => self::percentile( $ttfbs, 50 ),
			'p75Ttfb'        => self::percentile( $ttfbs, 75 ),
			'topPaths'       => array_slice( $top_paths, 0, $max_top_paths ),
			'pathGroups'     => $is_pro ? array_slice( $path_groups, 0, 8 ) : array(),
			'dailySeries'    => $is_pro ? $daily_series : array(),
			'modeBreakdown'  => $is_pro ? $mode_breakdown : array(),
			'csvEnabled'     => $is_pro,
			'proRequired'    => array(
				'dailySeries'   => ! $is_pro,
				'pathGroups'    => ! $is_pro,
				'modeBreakdown' => ! $is_pro,
				'csvExport'     => ! $is_pro,
			),
			'privacySummary' => __( 'Speculation Pilot stores local paths and timing numbers only. It does not store IP addresses, cookies, user IDs, query strings, form values, emails, full URLs, or user agents.', 'speculation-pilot' ),
		);
	}

	/**
	 * Deletes all local measurement rows.
	 */
	public function clear(): int {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );

		$wpdb->query( 'TRUNCATE TABLE ' . self::table_name() );

		return $count;
	}

	/**
	 * Cleans old measurements.
	 */
	public function cleanup(): void {
		global $wpdb;

		$settings = $this->settings->get();
		$days     = max( 1, (int) $settings['retention_days'] );
		$before   = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $days ) );

		if ( ! self::table_exists() ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::table_name() . ' WHERE created_at < %s',
				$before
			)
		);
	}

	/**
	 * Sanitizes a measurement event.
	 *
	 * @param array<string, mixed> $payload Raw payload.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function sanitize_event( array $payload ) {
		$current_path = isset( $payload['currentPath'] ) ? self::sanitize_path( (string) $payload['currentPath'] ) : '';

		if ( '' === $current_path ) {
			return new \WP_Error( 'invalid_current_path', __( 'A current local path is required.', 'speculation-pilot' ) );
		}

		$navigation_type = isset( $payload['navigationType'] ) ? sanitize_key( (string) $payload['navigationType'] ) : '';
		$navigation_type = in_array( $navigation_type, array( 'navigate', 'reload', 'back_forward', 'prerender' ), true ) ? $navigation_type : 'navigate';

		$settings = $this->settings->get();

		return array(
			'current_path'     => $current_path,
			'previous_path'    => isset( $payload['previousPath'] ) ? self::sanitize_path( (string) $payload['previousPath'] ) : '',
			'navigation_type'  => $navigation_type,
			'duration'         => $this->sanitize_metric( $payload['duration'] ?? null ),
			'ttfb'             => $this->sanitize_metric( $payload['ttfb'] ?? null ),
			'dom_interactive'  => $this->sanitize_metric( $payload['domInteractive'] ?? null ),
			'load_complete'    => $this->sanitize_metric( $payload['loadComplete'] ?? null ),
			'activation_start' => $this->sanitize_metric( $payload['activationStart'] ?? null ),
			'was_prerender'    => ! empty( $payload['wasPrerender'] ) ? 1 : 0,
			'mode'             => sanitize_key( (string) $settings['mode'] ),
			'eagerness'        => sanitize_key( (string) $settings['eagerness'] ),
		);
	}

	/**
	 * Sanitizes a local path by removing host, query, and fragment.
	 *
	 * @param string $path Raw path.
	 */
	public static function sanitize_path( string $path ): string {
		$path = trim( $path );

		if ( '' === $path || strlen( $path ) > 2048 ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $path ) ) {
			$parsed = wp_parse_url( $path );

			if ( ! self::is_same_site_url( $parsed ) ) {
				return '';
			}

			$path   = isset( $parsed['path'] ) ? (string) $parsed['path'] : '';
		}

		$path = strtok( $path, '?#' );
		$path = is_string( $path ) ? $path : '';

		if ( '' === $path ) {
			$path = '/';
		}

		if ( '/' !== $path[0] ) {
			$path = '/' . $path;
		}

		$path = preg_replace( '#/+#', '/', $path );
		$path = substr( (string) $path, 0, 255 );

		return sanitize_text_field( $path );
	}

	/**
	 * Checks whether the custom table exists.
	 */
	public static function table_exists(): bool {
		global $wpdb;

		$table_name = self::table_name();

		return $table_name === $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);
	}

	/**
	 * Returns an empty report payload.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 * @return array<string, mixed>
	 */
	private function empty_report( array $settings ): array {
		return array(
			'enabled'        => (bool) $settings['measurement_enabled'],
			'retentionDays'  => max( 1, (int) $settings['retention_days'] ),
			'sampleCount'    => 0,
			'p50Duration'    => null,
			'p75Duration'    => null,
			'p50Ttfb'        => null,
			'p75Ttfb'        => null,
			'topPaths'       => array(),
			'pathGroups'     => array(),
			'dailySeries'    => array(),
			'modeBreakdown'  => array(),
			'privacySummary' => __( 'Speculation Pilot stores local paths and timing numbers only. It does not store IP addresses, cookies, user IDs, query strings, form values, emails, full URLs, or user agents.', 'speculation-pilot' ),
		);
	}

	/**
	 * Builds a compact group label from a local path.
	 *
	 * @param string $path Local path.
	 */
	private static function path_group( string $path ): string {
		$path = trim( $path, '/' );

		if ( '' === $path ) {
			return '/';
		}

		$parts = explode( '/', $path );

		return '/' . sanitize_title( (string) $parts[0] ) . '/*';
	}

	/**
	 * Confirms a parsed URL belongs to this site.
	 *
	 * @param array<string, mixed>|false $parsed Parsed URL.
	 */
	private static function is_same_site_url( $parsed ): bool {
		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return false;
		}

		$home = wp_parse_url( home_url( '/' ) );

		if ( ! is_array( $home ) || empty( $home['host'] ) ) {
			return false;
		}

		$parsed_host = strtolower( (string) $parsed['host'] );
		$home_host   = strtolower( (string) $home['host'] );

		if ( $parsed_host !== $home_host ) {
			return false;
		}

		return true;
	}

	/**
	 * Sanitizes a timing metric.
	 *
	 * @param mixed $value Raw value.
	 * @return float|null
	 */
	private function sanitize_metric( $value ) {
		if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
			return null;
		}

		$value = (float) $value;

		if ( $value < 0 || $value > 600000 ) {
			return null;
		}

		return round( $value, 2 );
	}

	/**
	 * Checks anonymous measurement rate limits.
	 */
	private function passes_rate_limit(): bool {
		$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$hash     = hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
		$key      = 'speculation_pilot_rate_' . substr( $hash, 0, 20 );
		$count    = (int) get_transient( $key );
		$max_hits = 60;

		if ( $count >= $max_hits ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Returns percentile for a number set.
	 *
	 * @param array<int, float> $values Values.
	 * @param int              $percentile Percentile.
	 * @return float|null
	 */
	public static function percentile( array $values, int $percentile ) {
		if ( empty( $values ) ) {
			return null;
		}

		sort( $values, SORT_NUMERIC );
		$count = count( $values );
		$rank  = ( $percentile / 100 ) * ( $count - 1 );
		$low   = (int) floor( $rank );
		$high  = (int) ceil( $rank );

		if ( $low === $high ) {
			return round( $values[ $low ], 2 );
		}

		$weight = $rank - $low;
		$value  = $values[ $low ] * ( 1 - $weight ) + $values[ $high ] * $weight;

		return round( $value, 2 );
	}
}
