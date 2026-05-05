<?php
/**
 * Settings tests.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot\Tests;

use SpeculationPilot\Settings;
use WP_UnitTestCase;

/**
 * @covers \SpeculationPilot\Settings
 */
final class SettingsTest extends WP_UnitTestCase {
	/**
	 * Invalid modes are replaced by defaults.
	 */
	public function test_invalid_mode_falls_back_to_default(): void {
		$settings = new Settings();
		$clean    = $settings->sanitize(
			array(
				'mode' => 'turbo',
			)
		);

		$this->assertSame( 'prefetch', $clean['mode'] );
	}

	/**
	 * Exclusion paths are normalized.
	 */
	public function test_exclusion_paths_are_normalized(): void {
		$this->assertSame( '/checkout/*', Settings::sanitize_exclusion_path( ' checkout/* ' ) );
	}

	/**
	 * Retention days remain bounded.
	 */
	public function test_retention_days_are_bounded(): void {
		$settings = new Settings();
		$clean    = $settings->sanitize(
			array(
				'retention_days' => 9999,
			)
		);

		$this->assertSame( 365, $clean['retention_days'] );
	}
}

