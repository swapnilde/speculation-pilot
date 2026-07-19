<?php
/**
 * AdminBarDebugger tests.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot\Tests;

use SpeculationPilot\AdminBarDebugger;
use SpeculationPilot\SafetyEngine;
use SpeculationPilot\Settings;
use WP_UnitTestCase;

/**
 * @covers \SpeculationPilot\AdminBarDebugger
 */
final class AdminBarDebuggerTest extends WP_UnitTestCase {
	public function test_registers_hooks(): void {
		$settings = new Settings();
		$safety   = new SafetyEngine( $settings );
		$debugger = new AdminBarDebugger( $settings, $safety );

		$debugger->register();

		$this->assertSame( 99, has_action( 'admin_bar_menu', array( $debugger, 'add_admin_bar_node' ) ) );
		$this->assertNotFalse( has_action( 'wp_enqueue_scripts', array( $debugger, 'enqueue_debug_assets' ) ) );
	}
}
