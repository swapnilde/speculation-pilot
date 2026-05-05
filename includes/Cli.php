<?php
/**
 * WP-CLI commands.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot;

/**
 * Provides operational commands for developers and agencies.
 */
final class Cli {
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
	 * Diagnostics service.
	 *
	 * @var Diagnostics
	 */
	private $diagnostics;

	/**
	 * Measurements service.
	 *
	 * @var Measurements
	 */
	private $measurements;

	/**
	 * Constructor.
	 *
	 * @param Settings     $settings Settings service.
	 * @param SafetyEngine $safety Safety engine.
	 * @param Diagnostics  $diagnostics Diagnostics service.
	 * @param Measurements $measurements Measurements service.
	 */
	public function __construct( Settings $settings, SafetyEngine $safety, Diagnostics $diagnostics, Measurements $measurements ) {
		$this->settings     = $settings;
		$this->safety       = $safety;
		$this->diagnostics  = $diagnostics;
		$this->measurements = $measurements;
	}

	/**
	 * Registers the command namespace.
	 *
	 * @param Settings     $settings Settings service.
	 * @param SafetyEngine $safety Safety engine.
	 * @param Diagnostics  $diagnostics Diagnostics service.
	 * @param Measurements $measurements Measurements service.
	 */
	public static function register( Settings $settings, SafetyEngine $safety, Diagnostics $diagnostics, Measurements $measurements ): void {
		\WP_CLI::add_command( 'speculation-pilot', new self( $settings, $safety, $diagnostics, $measurements ) );
	}

	/**
	 * Shows current plugin settings.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Accepted: table, json. Default: table.
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 */
	public function settings( array $args, array $assoc_args ): void {
		$settings = $this->settings->get();
		$format   = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';

		if ( 'json' === $format ) {
			\WP_CLI::line( wp_json_encode( $settings, JSON_PRETTY_PRINT ) );
			return;
		}

		$rows = array(
			array(
				'key'   => 'enabled',
				'value' => $settings['enabled'] ? 'yes' : 'no',
			),
			array(
				'key'   => 'mode',
				'value' => (string) $settings['mode'],
			),
			array(
				'key'   => 'eagerness',
				'value' => (string) $settings['eagerness'],
			),
			array(
				'key'   => 'preset',
				'value' => (string) $settings['preset'],
			),
			array(
				'key'   => 'measurement_enabled',
				'value' => $settings['measurement_enabled'] ? 'yes' : 'no',
			),
			array(
				'key'   => 'retention_days',
				'value' => (string) $settings['retention_days'],
			),
		);

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'key', 'value' ) );
	}

	/**
	 * Shows diagnostics in CI-friendly form.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Accepted: table, json. Default: table.
	 *
	 * [--fail-on-warning]
	 * : Exit non-zero when the overall status is warning or error.
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 */
	public function doctor( array $args, array $assoc_args ): void {
		$report = $this->diagnostics->get_report();
		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';

		if ( 'json' === $format ) {
			\WP_CLI::line( wp_json_encode( $report, JSON_PRETTY_PRINT ) );
		} else {
			\WP_CLI::line( 'Overall status: ' . $report['status'] );
			\WP_CLI\Utils\format_items( 'table', $report['items'], array( 'key', 'status', 'label', 'message' ) );
		}

		if ( 'error' === $report['status'] || ( ! empty( $assoc_args['fail-on-warning'] ) && 'warning' === $report['status'] ) ) {
			\WP_CLI::halt( 1 );
		}
	}

	/**
	 * Lists active exclusion paths.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Accepted: table, json. Default: table.
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 */
	public function exclusions( array $args, array $assoc_args ): void {
		if ( ! apply_filters( 'speculation_pilot_is_pro', false ) ) {
			\WP_CLI::error( 'The exclusions command requires Speculation Pilot Pro. Visit https://speculationpilot.com/pricing/ to upgrade.' );
		}

		$paths  = array_map(
			static function ( string $path ): array {
				return array( 'path' => $path );
			},
			$this->safety->get_exclusion_paths()
		);
		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';

		if ( 'json' === $format ) {
			\WP_CLI::line( wp_json_encode( $paths, JSON_PRETTY_PRINT ) );
			return;
		}

		\WP_CLI\Utils\format_items( 'table', $paths, array( 'path' ) );
	}

	/**
	 * Shows the local measurement report.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Accepted: table, json. Default: table.
	 *
	 * @param array<int, string>   $args Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 */
	public function report( array $args, array $assoc_args ): void {
		if ( ! apply_filters( 'speculation_pilot_is_pro', false ) ) {
			\WP_CLI::error( 'The report command requires Speculation Pilot Pro. Visit https://speculationpilot.com/pricing/ to upgrade.' );
		}

		$report = $this->measurements->get_report();
		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';

		if ( 'json' === $format ) {
			\WP_CLI::line( wp_json_encode( $report, JSON_PRETTY_PRINT ) );
			return;
		}

		\WP_CLI::line( 'Samples: ' . (string) $report['sampleCount'] );
		\WP_CLI::line( 'p50 duration: ' . $this->format_metric( $report['p50Duration'] ) );
		\WP_CLI::line( 'p75 duration: ' . $this->format_metric( $report['p75Duration'] ) );
		\WP_CLI::line( 'p75 TTFB: ' . $this->format_metric( $report['p75Ttfb'] ) );

		if ( ! empty( $report['topPaths'] ) ) {
			\WP_CLI\Utils\format_items( 'table', $report['topPaths'], array( 'path', 'samples', 'p75Duration', 'prerenders' ) );
		}
	}

	/**
	 * Formats a metric for CLI display.
	 *
	 * @param mixed $value Metric value.
	 */
	private function format_metric( $value ): string {
		return null === $value ? 'n/a' : (string) round( (float) $value ) . ' ms';
	}
}

