<?php
/**
 * Safety engine tests.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot\Tests;

use SpeculationPilot\SafetyEngine;
use SpeculationPilot\Settings;
use WP_UnitTestCase;

/**
 * @covers \SpeculationPilot\SafetyEngine
 */
final class SafetyEngineTest extends WP_UnitTestCase {
	/**
	 * Safe defaults include commerce exclusions.
	 */
	public function test_safe_defaults_include_checkout_paths(): void {
		$settings = new Settings();
		update_option( SPECULATION_PILOT_OPTION, Settings::defaults() );

		$engine = new SafetyEngine( $settings );
		$paths  = $engine->get_exclusion_paths();

		$this->assertContains( '/checkout/*', $paths );
		$this->assertContains( '/cart/*', $paths );
	}

	public function test_returns_default_selector_exclusions(): void {
		$settings  = new Settings();
		$engine    = new SafetyEngine( $settings );
		$selectors = $engine->get_exclusion_selectors();

		$this->assertContains( 'a[rel~="nofollow"]', $selectors );
		$this->assertContains( 'a[download]', $selectors );
		$this->assertContains( '.no-speculate', $selectors );
	}
}
