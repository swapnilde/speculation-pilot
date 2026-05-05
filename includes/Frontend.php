<?php
/**
 * Frontend asset integration.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot;

/**
 * Enqueues frontend measurement assets.
 */
final class Frontend {
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_measurement_script' ) );
	}

	/**
	 * Enqueues measurement script when enabled.
	 */
	public function enqueue_measurement_script(): void {
		$settings = $this->settings->get();

		if ( empty( $settings['enabled'] ) || empty( $settings['measurement_enabled'] ) || is_admin() || is_user_logged_in() ) {
			return;
		}

		$asset_file = SPECULATION_PILOT_PATH . 'build/frontend/measurement.asset.php';
		$asset      = is_readable( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array(),
				'version'      => SPECULATION_PILOT_VERSION,
			);

		wp_enqueue_script(
			'speculation-pilot-measurement',
			SPECULATION_PILOT_URL . 'build/frontend/measurement.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_add_inline_script(
			'speculation-pilot-measurement',
			'window.SpeculationPilotMeasurement = ' . wp_json_encode(
				array(
					'endpoint'  => esc_url_raw( rest_url( 'speculation-pilot/v1/measurement' ) ),
					'mode'      => $settings['mode'],
					'eagerness' => $settings['eagerness'],
					'version'   => SPECULATION_PILOT_VERSION,
				)
			) . ';',
			'before'
		);
	}
}

