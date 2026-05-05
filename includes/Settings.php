<?php
/**
 * Settings model.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot;

/**
 * Reads, sanitizes, and persists plugin settings.
 */
final class Settings {
	public const MODES = array( 'core_default', 'disabled', 'prefetch', 'prerender' );
	public const EAGERNESS = array( 'core_default', 'conservative', 'moderate', 'eager' );
	public const PRESETS = array( 'safe', 'balanced', 'aggressive_lab', 'custom' );

	/**
	 * Returns default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'                => true,
			'mode'                   => 'prefetch',
			'eagerness'              => 'conservative',
			'preset'                 => 'safe',
			'exclusions'             => array(),
			'integrations'           => array(
				'woocommerce'  => true,
				'edd'          => false,
				'membership'   => false,
				'lms'          => false,
				'multilingual' => false,
				'cache'        => false,
			),
			'measurement_enabled'    => false,
			'retention_days'         => 14,
			'cleanup_on_uninstall'   => false,
			'prerender_warning_seen' => false,
		);
	}

	/**
	 * Returns current settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$raw = get_option( SPECULATION_PILOT_OPTION, array() );

		return $this->sanitize( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Updates settings.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, mixed>
	 */
	public function update( array $input ): array {
		$current = $this->get();
		$merged  = array_replace_recursive( $current, $input );
		$clean   = $this->sanitize( $merged );

		update_option( SPECULATION_PILOT_OPTION, $clean, false );

		return $clean;
	}

	/**
	 * Sanitizes a settings payload.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, mixed>
	 */
	public function sanitize( array $input ): array {
		$defaults = self::defaults();
		$settings = array_replace_recursive( $defaults, $input );
		$mode     = isset( $settings['mode'] ) ? sanitize_key( (string) $settings['mode'] ) : $defaults['mode'];
		$eagerness = isset( $settings['eagerness'] ) ? sanitize_key( (string) $settings['eagerness'] ) : $defaults['eagerness'];
		$preset    = isset( $settings['preset'] ) ? sanitize_key( (string) $settings['preset'] ) : $defaults['preset'];

		if ( ! in_array( $mode, self::MODES, true ) ) {
			$mode = $defaults['mode'];
		}

		if ( ! in_array( $eagerness, self::EAGERNESS, true ) ) {
			$eagerness = $defaults['eagerness'];
		}

		if ( ! in_array( $preset, self::PRESETS, true ) ) {
			$preset = $defaults['preset'];
		}

		$integrations = is_array( $settings['integrations'] ) ? $settings['integrations'] : array();
		$clean_integrations = array();
		foreach ( $defaults['integrations'] as $key => $default_value ) {
			$clean_integrations[ $key ] = isset( $integrations[ $key ] )
				? rest_sanitize_boolean( $integrations[ $key ] )
				: (bool) $default_value;
		}

		$exclusions = array();
		if ( isset( $settings['exclusions'] ) && is_array( $settings['exclusions'] ) ) {
			foreach ( $settings['exclusions'] as $exclusion ) {
				$path = self::sanitize_exclusion_path( (string) $exclusion );
				if ( '' !== $path ) {
					$exclusions[] = $path;
				}
			}
		}

		$exclusions = array_values( array_unique( $exclusions ) );
		sort( $exclusions );

		return array(
			'enabled'                => rest_sanitize_boolean( $settings['enabled'] ),
			'mode'                   => $mode,
			'eagerness'              => $eagerness,
			'preset'                 => $preset,
			'exclusions'             => $exclusions,
			'integrations'           => $clean_integrations,
			'measurement_enabled'    => rest_sanitize_boolean( $settings['measurement_enabled'] ),
			'retention_days'         => min( 365, max( 1, absint( $settings['retention_days'] ) ) ),
			'cleanup_on_uninstall'   => rest_sanitize_boolean( $settings['cleanup_on_uninstall'] ),
			'prerender_warning_seen' => rest_sanitize_boolean( $settings['prerender_warning_seen'] ),
		);
	}

	/**
	 * Sanitizes a path pattern for Core's exclusion filter.
	 *
	 * @param string $path Raw path pattern.
	 */
	public static function sanitize_exclusion_path( string $path ): string {
		$path = trim( wp_strip_all_tags( $path ) );

		if ( '' === $path || strlen( $path ) > 255 ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $path ) ) {
			$parsed = wp_parse_url( $path );
			$path   = isset( $parsed['path'] ) ? (string) $parsed['path'] : '';
		}

		if ( '' === $path ) {
			return '';
		}

		if ( '/' !== $path[0] ) {
			$path = '/' . $path;
		}

		$path = preg_replace( '#\s+#', '', $path );
		$path = preg_replace( '#/+#', '/', (string) $path );
		$path = preg_replace( '#[^A-Za-z0-9/_.,~:;=@&%*?()!$+\-\\\\]#', '', (string) $path );

		return substr( (string) $path, 0, 255 );
	}
}
