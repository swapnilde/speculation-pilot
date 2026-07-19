<?php
/**
 * Diagnostics service.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot;

/**
 * Produces admin diagnostics and compatibility data.
 */
final class Diagnostics {
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Safety engine.
	 *
	 * @var SafetyEngine
	 */
	private $safety;

	/**
	 * Constructor.
	 *
	 * @param Settings     $settings Settings service.
	 * @param SafetyEngine $safety Safety engine.
	 */
	public function __construct( Settings $settings, SafetyEngine $safety ) {
		$this->settings = $settings;
		$this->safety   = $safety;
	}

	/**
	 * Returns a diagnostics payload.
	 *
	 * @return array<string, mixed>
	 */
	public function get_report(): array {
		global $wp_version;

		$settings        = $this->settings->get();
		$exclusions      = $this->safety->get_exclusion_paths( (string) $settings['mode'] );
		$items           = array();
		$wp_compatible   = version_compare( (string) $wp_version, SPECULATION_PILOT_MIN_WP, '>=' );
		$php_compatible  = version_compare( PHP_VERSION, SPECULATION_PILOT_MIN_PHP, '>=' );
		$has_core_filter = function_exists( 'wp_get_speculation_rules_configuration' );
		$pretty_links    = (bool) get_option( 'permalink_structure' );
		$table_ready     = Measurements::table_exists();

		$items[] = array(
			'key'     => 'wordpress_version',
			'status'  => $wp_compatible ? 'ok' : 'error',
			'label'   => __( 'WordPress version', 'speculation-pilot' ),
			'message' => $wp_compatible
				/* translators: %s: current WordPress version. */
				? sprintf( __( 'WordPress %s supports speculative loading hooks.', 'speculation-pilot' ), (string) $wp_version )
				/* translators: 1: minimum required WordPress version, 2: current WordPress version. */
				: sprintf( __( 'WordPress %1$s or newer is required. Current version: %2$s.', 'speculation-pilot' ), SPECULATION_PILOT_MIN_WP, (string) $wp_version ),
		);

		$items[] = array(
			'key'     => 'php_version',
			'status'  => $php_compatible ? 'ok' : 'error',
			'label'   => __( 'PHP version', 'speculation-pilot' ),
			'message' => $php_compatible
				/* translators: %s: current PHP version. */
				? sprintf( __( 'PHP %s is supported.', 'speculation-pilot' ), PHP_VERSION )
				/* translators: 1: minimum required PHP version, 2: current PHP version. */
				: sprintf( __( 'PHP %1$s or newer is required. Current version: %2$s.', 'speculation-pilot' ), SPECULATION_PILOT_MIN_PHP, PHP_VERSION ),
		);

		$is_pro  = (bool) apply_filters( 'speculation_pilot_is_pro', false );
		$items[] = array(
			'key'     => 'plan',
			'status'  => $is_pro ? 'ok' : 'info',
			'label'   => __( 'Plan', 'speculation-pilot' ),
			'message' => $is_pro
				? (string) apply_filters( 'speculation_pilot_license_message', __( 'Pro license is active.', 'speculation-pilot' ) )
				: __( 'You are using the free version. Upgrade to Pro for advanced reports and integrations.', 'speculation-pilot' ),
		);

		$items[] = array(
			'key'     => 'core_hooks',
			'status'  => $has_core_filter ? 'ok' : 'error',
			'label'   => __( 'Core speculative loading API', 'speculation-pilot' ),
			'message' => $has_core_filter
				? __( 'WordPress Core speculative loading functions are available.', 'speculation-pilot' )
				: __( 'The Core speculative loading functions were not found.', 'speculation-pilot' ),
		);

		$items[] = array(
			'key'     => 'pretty_permalinks',
			'status'  => $pretty_links ? 'ok' : 'warning',
			'label'   => __( 'Pretty permalinks', 'speculation-pilot' ),
			'message' => $pretty_links
				? __( 'Pretty permalinks are enabled.', 'speculation-pilot' )
				: __( 'WordPress Core disables speculative loading when pretty permalinks are off.', 'speculation-pilot' ),
		);

		$items[] = array(
			'key'     => 'logged_in',
			'status'  => 'info',
			'label'   => __( 'Logged-in visitors', 'speculation-pilot' ),
			'message' => __( 'WordPress Core disables speculative loading for logged-in visitors. Test frontend rules in a logged-out browser session.', 'speculation-pilot' ),
		);

		$cache_plugins = $this->get_active_cache_plugins();
		if ( ! empty( $cache_plugins ) ) {
			$items[] = array(
				'key'     => 'cache_plugins',
				'status'  => 'info',
				'label'   => __( 'Caching and optimization plugins', 'speculation-pilot' ),
				'message' => sprintf(
					/* translators: %s: comma-separated plugin names. */
					__( 'Detected: %s. Confirm speculative rules are not removed by HTML optimization or cache minification settings.', 'speculation-pilot' ),
					implode( ', ', $cache_plugins )
				),
			);
		}

		$conflict_plugins = $this->get_active_prefetch_conflict_plugins();
		if ( ! empty( $conflict_plugins ) ) {
			$items[] = array(
				'key'     => 'prefetch_conflicts',
				'status'  => 'warning',
				'label'   => __( 'Legacy prefetch plugin conflict', 'speculation-pilot' ),
				'message' => sprintf(
					/* translators: %s: comma-separated plugin names. */
					__( 'Detected legacy prefetching plugins: %s. Running client-side JS prefetchers alongside Speculation Pilot can cause duplicate network requests. Consider disabling legacy prefetch plugins.', 'speculation-pilot' ),
					implode( ', ', $conflict_plugins )
				),
			);
		}

		if ( empty( $settings['enabled'] ) || 'disabled' === $settings['mode'] ) {
			$items[] = array(
				'key'     => 'plugin_mode',
				'status'  => 'warning',
				'label'   => __( 'Plugin mode', 'speculation-pilot' ),
				'message' => __( 'Speculation Pilot is currently disabled.', 'speculation-pilot' ),
			);
		}

		if ( ! empty( $settings['measurement_enabled'] ) ) {
			$items[] = array(
				'key'     => 'measurement_table',
				'status'  => $table_ready ? 'ok' : 'warning',
				'label'   => __( 'Measurement storage', 'speculation-pilot' ),
				'message' => $table_ready
					? __( 'The local measurement table is available.', 'speculation-pilot' )
					: __( 'The local measurement table is not available yet. It will be recreated automatically on the next measurement request.', 'speculation-pilot' ),
			);
		}

		if ( 'prerender' === $settings['mode'] ) {
			$items[] = array(
				'key'     => 'prerender_warning',
				'status'  => 'warning',
				'label'   => __( 'Prerender safety', 'speculation-pilot' ),
				'message' => __( 'Prerender can run page JavaScript before navigation is completed. Keep carts, checkout, account, and state-changing URLs excluded.', 'speculation-pilot' ),
			);
		}

		$overall = 'ok';
		foreach ( $items as $item ) {
			if ( 'error' === $item['status'] ) {
				$overall = 'error';
				break;
			}

			if ( 'warning' === $item['status'] && 'error' !== $overall ) {
				$overall = 'warning';
			}
		}

		return array(
			'status'          => $overall,
			'items'           => $items,
			'settings'        => $settings,
			'effectiveConfig' => $this->get_effective_config_preview( $settings ),
			'exclusions'      => $exclusions,
			'integrations'    => $this->get_integrations(),
			'selectors'       => $this->get_selector_opt_outs( $settings ),
		);
	}

	/**
	 * Builds an effective configuration preview.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 * @return array<string, mixed>|null
	 */
	private function get_effective_config_preview( array $settings ) {
		if ( empty( $settings['enabled'] ) || 'disabled' === $settings['mode'] ) {
			return null;
		}

		return array(
			'mode'      => 'core_default' === $settings['mode'] ? 'prefetch' : $settings['mode'],
			'eagerness' => 'core_default' === $settings['eagerness'] ? 'conservative' : $settings['eagerness'],
			'preset'    => $settings['preset'],
		);
	}

	/**
	 * Returns integration statuses.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_integrations(): array {
		return array(
			'woocommerce' => array(
				'label'  => 'WooCommerce',
				'active' => class_exists( 'WooCommerce' ),
			),
			'edd'         => array(
				'label'  => 'Easy Digital Downloads',
				'active' => class_exists( 'Easy_Digital_Downloads' ),
			),
			'membership'  => array(
				'label'  => __( 'Membership plugins', 'speculation-pilot' ),
				'active' => class_exists( 'MemberPress' ) || defined( 'PMPRO_VERSION' ) || defined( 'PAID_MEMBERSHIPS_PRO_VERSION' ),
			),
			'lms'         => array(
				'label'  => __( 'LMS plugins', 'speculation-pilot' ),
				'active' => defined( 'LEARNDASH_VERSION' ) || defined( 'TUTOR_VERSION' ) || class_exists( 'LifterLMS' ),
			),
			'multilingual' => array(
				'label'  => __( 'Multilingual plugins', 'speculation-pilot' ),
				'active' => defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'POLYLANG_VERSION' ) || defined( 'TRP_PLUGIN_VERSION' ),
			),
			'cache'       => array(
				'label'  => __( 'Cache and optimization plugins', 'speculation-pilot' ),
				'active' => ! empty( $this->get_active_cache_plugins() ),
			),
		);
	}

	/**
	 * Returns detected caching and optimization plugins.
	 *
	 * @return array<int, string>
	 */
	private function get_active_cache_plugins(): array {
		$plugins = array();

		if ( defined( 'WP_ROCKET_VERSION' ) ) {
			$plugins[] = 'WP Rocket';
		}

		if ( defined( 'LSCWP_V' ) || class_exists( 'LiteSpeed_Cache' ) ) {
			$plugins[] = 'LiteSpeed Cache';
		}

		if ( defined( 'W3TC_VERSION' ) ) {
			$plugins[] = 'W3 Total Cache';
		}

		if ( defined( 'WPCACHEHOME' ) || function_exists( 'wp_cache_phase2' ) ) {
			$plugins[] = 'WP Super Cache';
		}

		if ( defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ) || class_exists( 'autoptimizeBase' ) ) {
			$plugins[] = 'Autoptimize';
		}

		if ( defined( 'BREEZE_VERSION' ) ) {
			$plugins[] = 'Breeze';
		}

		if ( defined( 'SG_CACHEPRESS_VERSION' ) ) {
			$plugins[] = 'SiteGround Optimizer';
		}

		return array_values( array_unique( $plugins ) );
	}

	/**
	 * Returns detected third-party prefetching plugins that could conflict.
	 *
	 * @return array<int, string>
	 */
	public function get_active_prefetch_conflict_plugins(): array {
		$plugins = array();

		if ( class_exists( 'InstantClick' ) || defined( 'INSTANTCLICK_VERSION' ) ) {
			$plugins[] = 'InstantClick';
		}

		if ( class_exists( 'Flying_Pages' ) || defined( 'FLYING_PAGES_VERSION' ) ) {
			$plugins[] = 'Flying Pages';
		}

		if ( class_exists( 'Quicklink' ) || defined( 'QUICKLINK_VERSION' ) ) {
			$plugins[] = 'Quicklink';
		}

		if ( class_exists( 'Instant_Page' ) || defined( 'INSTANT_PAGE_VERSION' ) ) {
			$plugins[] = 'Instant.page';
		}

		if ( defined( 'PERFMATTERS_VERSION' ) ) {
			$plugins[] = 'Perfmatters Instant Page';
		}

		if ( defined( 'FLYING_SCRIPTS_VERSION' ) ) {
			$plugins[] = 'Flying Scripts';
		}

		return array_values( array_unique( $plugins ) );
	}

	/**
	 * Returns selector opt-outs used by Core.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 * @return array<int, string>
	 */
	private function get_selector_opt_outs( array $settings ): array {
		$mode = (string) $settings['mode'];

		if ( 'core_default' === $mode || 'disabled' === $mode ) {
			$mode = 'prefetch';
		}

		$selectors = array(
			'a[rel~="nofollow"]',
			'.no-' . $mode,
			'.no-' . $mode . ' a',
		);

		if ( 'prerender' === $mode ) {
			$selectors[] = '.no-prefetch';
			$selectors[] = '.no-prefetch a';
		}

		return $selectors;
	}
}
