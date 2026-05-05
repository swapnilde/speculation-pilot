<?php
/**
 * Main plugin runtime.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot;

/**
 * Coordinates plugin services.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

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
	 * Measurements service.
	 *
	 * @var Measurements
	 */
	private $measurements;

	/**
	 * Returns singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings     = new Settings();
		$this->safety       = new SafetyEngine( $this->settings );
		$this->measurements = new Measurements( $this->settings );
	}

	/**
	 * Boots all services.
	 */
	public function boot(): void {
		$diagnostics = new Diagnostics( $this->settings, $this->safety );

		( new CoreIntegration( $this->settings, $this->safety ) )->register();
		( new RestApi( $this->settings, $this->safety, $diagnostics, $this->measurements ) )->register();
		( new Admin( $this->settings ) )->register();
		( new Frontend( $this->settings ) )->register();
		( new SiteHealth( $diagnostics ) )->register();
		$this->measurements->register();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Cli::register( $this->settings, $this->safety, $diagnostics, $this->measurements );
		}
	}
}
