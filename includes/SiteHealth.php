<?php
/**
 * Site Health integration.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot;

/**
 * Adds a Site Health test for speculative loading readiness.
 */
final class SiteHealth {
	/**
	 * Diagnostics service.
	 *
	 * @var Diagnostics
	 */
	private $diagnostics;

	/**
	 * Constructor.
	 *
	 * @param Diagnostics $diagnostics Diagnostics service.
	 */
	public function __construct( Diagnostics $diagnostics ) {
		$this->diagnostics = $diagnostics;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_filter( 'site_status_tests', array( $this, 'add_test' ) );
	}

	/**
	 * Adds the direct Site Health test.
	 *
	 * @param array<string, mixed> $tests Existing tests.
	 * @return array<string, mixed>
	 */
	public function add_test( array $tests ): array {
		if ( ! isset( $tests['direct'] ) || ! is_array( $tests['direct'] ) ) {
			$tests['direct'] = array();
		}

		$tests['direct']['speculation_pilot'] = array(
			'label' => __( 'Speculation Pilot readiness', 'speculation-pilot' ),
			'test'  => array( $this, 'run_test' ),
		);

		return $tests;
	}

	/**
	 * Runs the Site Health test.
	 *
	 * @return array<string, mixed>
	 */
	public function run_test(): array {
		$report = $this->diagnostics->get_report();
		$status = $this->map_status( (string) $report['status'] );

		return array(
			'label'       => $this->get_label( (string) $report['status'] ),
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Performance', 'speculation-pilot' ),
				'color' => 'blue',
			),
			'description' => sprintf(
				'<p>%s</p>%s',
				esc_html__( 'Speculation Pilot checked WordPress speculative loading compatibility, settings, and safety warnings.', 'speculation-pilot' ),
				$this->render_items( (array) $report['items'] )
			),
			'actions'     => sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'options-general.php?page=speculation-pilot' ) ),
				esc_html__( 'Open Speculation Pilot settings', 'speculation-pilot' )
			),
			'test'        => 'speculation_pilot',
		);
	}

	/**
	 * Maps diagnostics status to Site Health status.
	 *
	 * @param string $status Diagnostics status.
	 */
	private function map_status( string $status ): string {
		if ( 'error' === $status ) {
			return 'critical';
		}

		if ( 'warning' === $status ) {
			return 'recommended';
		}

		return 'good';
	}

	/**
	 * Returns a human label.
	 *
	 * @param string $status Diagnostics status.
	 */
	private function get_label( string $status ): string {
		if ( 'error' === $status ) {
			return __( 'Speculation Pilot has blocking compatibility issues', 'speculation-pilot' );
		}

		if ( 'warning' === $status ) {
			return __( 'Speculation Pilot has recommendations to review', 'speculation-pilot' );
		}

		return __( 'Speculation Pilot is ready', 'speculation-pilot' );
	}

	/**
	 * Renders diagnostic items.
	 *
	 * @param array<int, array<string, mixed>> $items Diagnostic items.
	 */
	private function render_items( array $items ): string {
		$output = '<ul>';

		foreach ( $items as $item ) {
			$output .= sprintf(
				'<li><strong>%s:</strong> %s</li>',
				esc_html( (string) $item['label'] ),
				esc_html( (string) $item['message'] )
			);
		}

		$output .= '</ul>';

		return $output;
	}
}

