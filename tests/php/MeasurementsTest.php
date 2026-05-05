<?php
/**
 * Measurement tests.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot\Tests;

use SpeculationPilot\Measurements;
use WP_UnitTestCase;

/**
 * @covers \SpeculationPilot\Measurements
 */
final class MeasurementsTest extends WP_UnitTestCase {
	/**
	 * Ensures full URLs are reduced to local paths.
	 */
	public function test_sanitize_path_strips_query_and_fragment(): void {
		$this->assertSame( '/shop/product/', Measurements::sanitize_path( home_url( '/shop/product/?coupon=secret#reviews' ) ) );
	}

	/**
	 * Ensures relative values become absolute paths.
	 */
	public function test_sanitize_path_adds_leading_slash(): void {
		$this->assertSame( '/shop/product/', Measurements::sanitize_path( 'shop/product/' ) );
	}

	/**
	 * Ensures external full URLs are rejected.
	 */
	public function test_sanitize_path_rejects_external_urls(): void {
		$this->assertSame( '', Measurements::sanitize_path( 'https://external.example/shop/?email=test@example.com' ) );
	}
}
