<?php
/**
 * Admin Bar live debug overlay integration.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot;

/**
 * Renders live speculative loading diagnostics in the WordPress Admin Bar.
 */
final class AdminBarDebugger {
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
	 * @param SafetyEngine $safety   Safety engine.
	 */
	public function __construct( Settings $settings, SafetyEngine $safety ) {
		$this->settings = $settings;
		$this->safety   = $safety;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_node' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_debug_assets' ) );
	}

	/**
	 * Adds the Speculation Pilot menu items to the Admin Bar.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function add_admin_bar_node( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) || is_admin() || ! is_admin_bar_showing() ) {
			return;
		}

		$settings = $this->settings->get();
		$mode     = (string) $settings['mode'];
		$eager    = (string) $settings['eagerness'];
		$enabled  = ! empty( $settings['enabled'] ) && 'disabled' !== $mode;

		$status_icon = $enabled ? '⚡' : '⏸️';
		$main_label  = sprintf( '%s Speculation Pilot', $status_icon );

		$wp_admin_bar->add_node(
			array(
				'id'    => 'speculation-pilot',
				'title' => '<span class="ab-icon dashicons dashicons-performance" style="top:2px;"></span><span class="ab-label">' . esc_html( $main_label ) . '</span>',
				'href'  => esc_url( admin_url( 'options-general.php?page=speculation-pilot' ) ),
				'meta'  => array(
					'title' => __( 'Speculation Pilot Diagnostics & Debugger', 'speculation-pilot' ),
				),
			)
		);

		// Status child node (updated dynamically via JS).
		$wp_admin_bar->add_node(
			array(
				'id'     => 'speculation-pilot-status',
				'parent' => 'speculation-pilot',
				'title'  => '<span id="sp-debug-status">' . ( $enabled ? esc_html__( 'Status: Checking browser activation...', 'speculation-pilot' ) : esc_html__( 'Status: Disabled in settings', 'speculation-pilot' ) ) . '</span>',
				'href'   => esc_url( admin_url( 'options-general.php?page=speculation-pilot' ) ),
			)
		);

		// Active configuration node.
		$config_label = sprintf(
			/* translators: 1: mode, 2: eagerness */
			__( 'Config: Mode %1$s / Eagerness %2$s', 'speculation-pilot' ),
			strtoupper( $mode ),
			strtoupper( $eager )
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'speculation-pilot-config',
				'parent' => 'speculation-pilot',
				'title'  => '<span>' . esc_html( $config_label ) . '</span>',
				'href'   => esc_url( admin_url( 'options-general.php?page=speculation-pilot' ) ),
			)
		);

		// Route Exclusion check node.
		$current_path = isset( $_SERVER['REQUEST_URI'] ) ? Measurements::sanitize_path( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) ) : '/';
		$exclusions   = $this->safety->get_exclusion_paths( 'prefetch' );
		$is_excluded  = false;
		$matched_rule = '';

		foreach ( $exclusions as $rule ) {
			if ( self::path_matches_rule( $current_path, $rule ) ) {
				$is_excluded  = true;
				$matched_rule = $rule;
				break;
			}
		}

		$route_label = $is_excluded
			? sprintf( __( 'Route: 🚫 Excluded (%s)', 'speculation-pilot' ), $matched_rule )
			: __( 'Route: ✅ Speculation Active', 'speculation-pilot' );

		$wp_admin_bar->add_node(
			array(
				'id'     => 'speculation-pilot-route',
				'parent' => 'speculation-pilot',
				'title'  => '<span id="sp-debug-route">' . esc_html( $route_label ) . '</span>',
				'href'   => esc_url( admin_url( 'options-general.php?page=speculation-pilot' ) ),
			)
		);

		// Candidate links counter node (updated via JS).
		$wp_admin_bar->add_node(
			array(
				'id'     => 'speculation-pilot-links',
				'parent' => 'speculation-pilot',
				'title'  => '<span id="sp-debug-links">' . esc_html__( 'DOM Candidates: Inspecting page links...', 'speculation-pilot' ) . '</span>',
				'href'   => '#',
			)
		);
	}

	/**
	 * Enqueues admin bar debugger script and styles.
	 */
	public function enqueue_debug_assets(): void {
		if ( ! current_user_can( 'manage_options' ) || is_admin() || ! is_admin_bar_showing() ) {
			return;
		}

		$asset_file = SPECULATION_PILOT_PATH . 'build/frontend/admin-bar-debugger.asset.php';
		$asset      = is_readable( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array(),
				'version'      => SPECULATION_PILOT_VERSION,
			);

		wp_enqueue_script(
			'speculation-pilot-admin-bar-debugger',
			SPECULATION_PILOT_URL . 'build/frontend/admin-bar-debugger.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'speculation-pilot-admin-bar-debugger',
			SPECULATION_PILOT_URL . 'build/frontend/admin-bar-debugger.css',
			array( 'admin-bar' ),
			SPECULATION_PILOT_VERSION
		);

		$settings   = $this->settings->get();
		$exclusions = $this->safety->get_exclusion_paths( 'prefetch' );

		wp_add_inline_script(
			'speculation-pilot-admin-bar-debugger',
			'window.SpeculationPilotDebugger = ' . wp_json_encode(
				array(
					'enabled'    => ! empty( $settings['enabled'] ) && 'disabled' !== $settings['mode'],
					'mode'       => $settings['mode'],
					'eagerness'  => $settings['eagerness'],
					'exclusions' => $exclusions,
					'homeUrl'    => home_url(),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Checks whether a path matches a wild-card or exact exclusion rule.
	 *
	 * @param string $path Local path.
	 * @param string $rule Exclusion rule.
	 */
	private static function path_matches_rule( string $path, string $rule ): bool {
		$rule = trim( $rule );
		if ( '' === $rule ) {
			return false;
		}

		if ( $path === $rule ) {
			return true;
		}

		if ( false !== strpos( $rule, '*' ) ) {
			$regex = '#^' . str_replace( '\*', '.*', preg_quote( $rule, '#' ) ) . '$#i';
			return (bool) preg_match( $regex, $path );
		}

		return false;
	}
}
