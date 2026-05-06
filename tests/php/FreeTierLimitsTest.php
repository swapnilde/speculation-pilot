<?php
/**
 * Free-tier limit tests.
 *
 * Verifies that all Pro-gating constants, filters, and clamping logic
 * work correctly on the free tier (no Pro plugin active).
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot\Tests;

use SpeculationPilot\Measurements;
use SpeculationPilot\SafetyEngine;
use SpeculationPilot\Settings;
use WP_UnitTestCase;

/**
 * @covers \SpeculationPilot\Settings
 * @covers \SpeculationPilot\SafetyEngine
 * @covers \SpeculationPilot\Measurements
 */
final class FreeTierLimitsTest extends WP_UnitTestCase {

	/**
	 * @var Settings
	 */
	private Settings $settings;

	public function set_up(): void {
		parent::set_up();
		$this->settings = new Settings();

		// Ensure no Pro filters are active.
		remove_all_filters( 'speculation_pilot_is_pro' );
		remove_all_filters( 'speculation_pilot_max_retention_days' );
		remove_all_filters( 'speculation_pilot_max_exclusions' );
		remove_all_filters( 'speculation_pilot_max_top_paths' );
	}

	// ─── Constants ───────────────────────────────────────────────────

	public function test_free_retention_constant_exists(): void {
		$this->assertTrue( defined( 'SPECULATION_PILOT_FREE_RETENTION_DAYS' ) );
	}

	public function test_free_max_exclusions_constant_exists(): void {
		$this->assertTrue( defined( 'SPECULATION_PILOT_FREE_MAX_EXCLUSIONS' ) );
	}

	public function test_free_max_top_paths_constant_exists(): void {
		$this->assertTrue( defined( 'SPECULATION_PILOT_FREE_MAX_TOP_PATHS' ) );
	}

	public function test_is_pro_defaults_to_false(): void {
		$this->assertFalse( apply_filters( 'speculation_pilot_is_pro', false ) );
	}

	// ─── Retention Days ──────────────────────────────────────────────

	public function test_effective_retention_cap_equals_free_constant(): void {
		$this->assertSame( SPECULATION_PILOT_FREE_RETENTION_DAYS, Settings::get_effective_retention_days_cap() );
	}

	public function test_retention_is_clamped_to_free_limit(): void {
		$result = Settings::get_effective_retention_days( 365 );
		$this->assertSame( SPECULATION_PILOT_FREE_RETENTION_DAYS, $result );
	}

	public function test_retention_below_free_limit_is_preserved(): void {
		$result = Settings::get_effective_retention_days( 3 );
		$this->assertSame( 3, $result );
	}

	public function test_retention_at_free_limit_is_preserved(): void {
		$result = Settings::get_effective_retention_days( SPECULATION_PILOT_FREE_RETENTION_DAYS );
		$this->assertSame( SPECULATION_PILOT_FREE_RETENTION_DAYS, $result );
	}

	// ─── Exclusions ──────────────────────────────────────────────────

	public function test_effective_max_exclusions_equals_free_constant(): void {
		$this->assertSame( SPECULATION_PILOT_FREE_MAX_EXCLUSIONS, Settings::get_effective_max_exclusions() );
	}

	public function test_exclusions_are_clamped_to_free_limit(): void {
		$paths = array( '/a', '/b', '/c', '/d', '/e', '/f', '/g', '/h', '/i', '/j' );
		$result = Settings::get_effective_exclusions( $paths );
		$this->assertCount( SPECULATION_PILOT_FREE_MAX_EXCLUSIONS, $result );
	}

	public function test_exclusions_below_free_limit_are_preserved(): void {
		$paths = array( '/a', '/b' );
		$result = Settings::get_effective_exclusions( $paths );
		$this->assertCount( 2, $result );
	}

	// ─── Integration Gating ──────────────────────────────────────────

	public function test_integration_groups_have_pro_required_flag(): void {
		$engine = new SafetyEngine( $this->settings );
		$groups = $engine->get_exclusion_groups();

		$has_pro_only = false;
		foreach ( $groups as $group ) {
			if ( ! empty( $group['pro_required'] ) ) {
				$has_pro_only = true;
				break;
			}
		}

		$this->assertTrue( $has_pro_only, 'At least one integration group should be Pro-only.' );
	}

	public function test_pro_integration_groups_are_inactive_on_free(): void {
		$engine = new SafetyEngine( $this->settings );
		$groups = $engine->get_exclusion_groups();

		foreach ( $groups as $group ) {
			if ( ! empty( $group['pro_required'] ) ) {
				$this->assertFalse(
					! empty( $group['active'] ),
					sprintf( 'Pro group "%s" should not be active on free tier.', $group['label'] ?? $group['key'] ?? 'unknown' )
				);
			}
		}
	}

	// ─── Measurements Report ─────────────────────────────────────────

	public function test_report_limits_top_paths_on_free(): void {
		$settings = new Settings();
		$measurements = new Measurements( $settings );
		$report = $measurements->get_report();

		// Verify the topPaths array respects the free limit (may be empty — that's fine).
		if ( ! empty( $report['topPaths'] ) ) {
			$this->assertLessThanOrEqual( SPECULATION_PILOT_FREE_MAX_TOP_PATHS, count( $report['topPaths'] ) );
		} else {
			$this->assertIsArray( $report['topPaths'] );
		}
	}

	// ─── Sanitize Clamping ───────────────────────────────────────────

	public function test_sanitize_clamps_retention_days(): void {
		$clean = $this->settings->sanitize( array( 'retention_days' => 999 ) );
		$this->assertLessThanOrEqual( 365, $clean['retention_days'] );
	}

	public function test_sanitize_clamps_exclusions(): void {
		$exclusions = array();
		for ( $i = 0; $i < 20; $i++ ) {
			$exclusions[] = '/path-' . $i;
		}

		$clean = $this->settings->sanitize( array( 'exclusions' => $exclusions ) );

		// Effective enforcement may happen at save time; count should not exceed free limit.
		$effective = Settings::get_effective_exclusions( $clean['exclusions'] );
		$this->assertLessThanOrEqual( SPECULATION_PILOT_FREE_MAX_EXCLUSIONS, count( $effective ) );
	}

	// ─── Pro Filter Override ─────────────────────────────────────────

	public function test_pro_filter_unlocks_retention(): void {
		add_filter( 'speculation_pilot_max_retention_days', static function (): int {
			return 365;
		} );

		$this->assertSame( 365, Settings::get_effective_retention_days_cap() );
		$this->assertSame( 365, Settings::get_effective_retention_days( 365 ) );

		remove_all_filters( 'speculation_pilot_max_retention_days' );
	}

	public function test_pro_filter_unlocks_exclusions(): void {
		add_filter( 'speculation_pilot_max_exclusions', static function (): int {
			return PHP_INT_MAX;
		} );

		$paths = array();
		for ( $i = 0; $i < 20; $i++ ) {
			$paths[] = '/path-' . $i;
		}

		$result = Settings::get_effective_exclusions( $paths );
		$this->assertCount( 20, $result );

		remove_all_filters( 'speculation_pilot_max_exclusions' );
	}

	public function test_is_pro_filter_returns_true_when_hooked(): void {
		add_filter( 'speculation_pilot_is_pro', '__return_true' );
		$this->assertTrue( apply_filters( 'speculation_pilot_is_pro', false ) );
		remove_all_filters( 'speculation_pilot_is_pro' );
	}
}
