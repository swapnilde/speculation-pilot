<?php
/**
 * Plugin singleton tests.
 *
 * Verifies that Plugin::instance() exposes public getter methods
 * needed by the Pro plugin.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot\Tests;

use SpeculationPilot\Measurements;
use SpeculationPilot\Plugin;
use SpeculationPilot\SafetyEngine;
use SpeculationPilot\Settings;
use WP_UnitTestCase;

/**
 * @covers \SpeculationPilot\Plugin
 */
final class PluginTest extends WP_UnitTestCase {

	public function test_instance_returns_singleton(): void {
		$a = Plugin::instance();
		$b = Plugin::instance();
		$this->assertSame( $a, $b );
	}

	public function test_settings_getter_returns_settings(): void {
		$plugin = Plugin::instance();
		$this->assertInstanceOf( Settings::class, $plugin->settings() );
	}

	public function test_safety_getter_returns_safety_engine(): void {
		$plugin = Plugin::instance();
		$this->assertInstanceOf( SafetyEngine::class, $plugin->safety() );
	}

	public function test_measurements_getter_returns_measurements(): void {
		$plugin = Plugin::instance();
		$this->assertInstanceOf( Measurements::class, $plugin->measurements() );
	}
}
