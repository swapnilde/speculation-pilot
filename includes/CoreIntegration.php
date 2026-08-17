<?php
/**
 * WordPress Core speculative loading integration.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot;

/**
 * Connects settings and safety rules to Core filters.
 */
final class CoreIntegration {
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
	 * Constructor.
	 *
	 * @param Settings     $settings Settings service.
	 * @param SafetyEngine $safety Safety engine.
	 */
	public function __construct( Settings $settings, SafetyEngine $safety ) {
		$this->settings = $settings;
		$this->safety   = $safety;
	}

	/**
	 * Registers hooks.
	 */
	public function register(): void {
		add_filter( 'wp_speculation_rules_configuration', array( $this, 'filter_configuration' ) );
		add_filter( 'wp_speculation_rules_href_exclude_paths', array( $this, 'filter_exclude_paths' ), 10, 2 );
		add_filter( 'wp_speculation_rules_no_selector_matches', array( $this, 'filter_exclude_selectors' ), 10, 2 );
	}

	/**
	 * Filters the Core speculation rules configuration.
	 *
	 * @param array<string, string>|null $config Core configuration.
	 * @return array<string, string>|null
	 */
	public function filter_configuration( $config ) {
		$settings = $this->settings->get();

		if ( empty( $settings['enabled'] ) ) {
			return $config;
		}

		if ( 'disabled' === $settings['mode'] ) {
			return null;
		}

		// Preserve Core's logged-in and no-pretty-permalink safety behavior.
		if ( null === $config ) {
			return null;
		}

		if ( ! is_array( $config ) ) {
			$config = array(
				'mode'      => 'auto',
				'eagerness' => 'auto',
			);
		}

		if ( 'core_default' !== $settings['mode'] ) {
			$config['mode'] = $settings['mode'];
		}

		if ( 'core_default' !== $settings['eagerness'] ) {
			$config['eagerness'] = $settings['eagerness'];
		}

		return $config;
	}

	/**
	 * Adds plugin-managed exclusion paths.
	 *
	 * @param array<int, string> $paths Existing paths.
	 * @param string             $mode Current mode.
	 * @return array<int, string>
	 */
	public function filter_exclude_paths( array $paths, string $mode ): array {
		$settings = $this->settings->get();

		if ( empty( $settings['enabled'] ) || 'disabled' === $settings['mode'] ) {
			return $paths;
		}

		return array_values(
			array_unique(
				array_merge(
					$paths,
					$this->safety->get_exclusion_paths( $mode )
				)
			)
		);
	}

	/**
	 * Adds plugin-managed selector exclusion rules.
	 *
	 * @param array<int, string> $selectors Existing CSS selectors.
	 * @param string             $mode Current mode.
	 * @return array<int, string>
	 */
	public function filter_exclude_selectors( array $selectors, string $mode ): array {
		unset( $mode );
		$settings = $this->settings->get();

		if ( empty( $settings['enabled'] ) || 'disabled' === $settings['mode'] ) {
			return $selectors;
		}

		return array_values(
			array_unique(
				array_merge(
					$selectors,
					$this->safety->get_exclusion_selectors()
				)
			)
		);
	}
}
