<?php
/**
 * Admin UI integration.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot;

/**
 * Registers the wp-admin app.
 */
final class Admin {
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SPECULATION_PILOT_FILE ), array( $this, 'add_action_links' ) );
	}

	/**
	 * Registers the admin menu page.
	 */
	public function register_menu(): void {
		add_options_page(
			__( 'Speculation Pilot', 'speculation-pilot' ),
			__( 'Speculation Pilot', 'speculation-pilot' ),
			'manage_options',
			'speculation-pilot',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueues admin assets.
	 *
	 * @param string $hook_suffix Current admin hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'settings_page_speculation-pilot' !== $hook_suffix ) {
			return;
		}

		$asset_file = SPECULATION_PILOT_PATH . 'build/admin/index.asset.php';
		$asset      = is_readable( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array( 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-i18n' ),
				'version'      => SPECULATION_PILOT_VERSION,
			);

		wp_enqueue_script(
			'speculation-pilot-admin',
			SPECULATION_PILOT_URL . 'build/admin/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'speculation-pilot-admin',
			SPECULATION_PILOT_URL . 'build/admin/index.css',
			array( 'wp-components' ),
			SPECULATION_PILOT_VERSION
		);

		wp_add_inline_script(
			'speculation-pilot-admin',
			'window.SpeculationPilotAdmin = ' . wp_json_encode(
				array(
					'root'      => esc_url_raw( rest_url( 'speculation-pilot/v1/' ) ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'adminUrl'  => esc_url_raw( admin_url( 'options-general.php?page=speculation-pilot' ) ),
					'version'   => SPECULATION_PILOT_VERSION,
					'constants' => array(
						'modes'      => Settings::MODES,
						'eagerness'  => Settings::EAGERNESS,
						'presets'    => Settings::PRESETS,
						'minWp'      => SPECULATION_PILOT_MIN_WP,
						'minPhp'     => SPECULATION_PILOT_MIN_PHP,
						'optionName' => SPECULATION_PILOT_OPTION,
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Renders the admin page mount.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap speculation-pilot-wrap">';
		echo '<div id="speculation-pilot-admin-root"></div>';
		echo '</div>';
	}

	/**
	 * Adds plugin row links.
	 *
	 * @param array<int, string> $links Existing links.
	 * @return array<int, string>
	 */
	public function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=speculation-pilot' ) ),
			esc_html__( 'Settings', 'speculation-pilot' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}

