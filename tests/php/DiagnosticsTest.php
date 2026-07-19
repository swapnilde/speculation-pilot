<?php
/**
 * Diagnostics tests.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot\Tests;

use SpeculationPilot\Diagnostics;
use SpeculationPilot\SafetyEngine;
use SpeculationPilot\Settings;
use WP_UnitTestCase;

/**
 * @covers \SpeculationPilot\Diagnostics
 */
final class DiagnosticsTest extends WP_UnitTestCase {
	public function test_get_report_returns_items(): void {
		$settings    = new Settings();
		$safety      = new SafetyEngine( $settings );
		$diagnostics = new Diagnostics( $settings, $safety );
		$report      = $diagnostics->get_report();

		$this->assertArrayHasKey( 'status', $report );
		$this->assertArrayHasKey( 'items', $report );
		$this->assertIsArray( $report['items'] );
	}

	public function test_detects_prefetch_conflict_plugins(): void {
		if ( ! defined( 'FLYING_PAGES_VERSION' ) ) {
			define( 'FLYING_PAGES_VERSION', '1.0.0' );
		}

		$settings    = new Settings();
		$safety      = new SafetyEngine( $settings );
		$diagnostics = new Diagnostics( $settings, $safety );
		$conflicts   = $diagnostics->get_active_prefetch_conflict_plugins();

		$this->assertContains( 'Flying Pages', $conflicts );
	}
}
