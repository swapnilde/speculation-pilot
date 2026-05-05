<?php
/**
 * Safety presets and exclusion generation.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot;

/**
 * Produces path exclusions for speculative loading.
 */
final class SafetyEngine {
	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Returns all active exclusion paths.
	 *
	 * @param string $mode Current mode.
	 * @return array<int, string>
	 */
	public function get_exclusion_paths( string $mode = 'prefetch' ): array {
		$settings = $this->settings->get();
		$groups   = $this->get_exclusion_groups( $mode );
		$paths    = array();

		foreach ( $groups as $group ) {
			if ( ! empty( $group['active'] ) ) {
				$paths = array_merge( $paths, $group['paths'] );
			}
		}

		$paths = array_merge( $paths, (array) $settings['exclusions'] );

		$paths = array_values(
			array_unique(
				array_filter(
					array_map( array( Settings::class, 'sanitize_exclusion_path' ), $paths )
				)
			)
		);

		sort( $paths );

		return $paths;
	}

	/**
	 * Returns grouped exclusions with metadata.
	 *
	 * @param string $mode Current mode.
	 * @return array<string, array<string, mixed>>
	 */
	public function get_exclusion_groups( string $mode = 'prefetch' ): array {
		$settings     = $this->settings->get();
		$integrations = (array) $settings['integrations'];
		$preset       = (string) $settings['preset'];

		$groups = array(
			'core_like'       => array(
				'label'  => __( 'WordPress safety paths', 'speculation-pilot' ),
				'active' => true,
				'paths'  => array(
					'/wp-login.php',
					'/wp-admin/*',
					'/wp-json/*',
					'/xmlrpc.php',
				),
			),
			'generic_account' => array(
				'label'  => __( 'Account and state-changing paths', 'speculation-pilot' ),
				'active' => true,
				'paths'  => array(
					'/login/*',
					'/logout/*',
					'/account/*',
					'/dashboard/*',
					'/profile/*',
					'/register/*',
					'/reset-password/*',
					'/lost-password/*',
					'/password-reset/*',
				),
			),
			'commerce'        => array(
				'label'  => __( 'Cart, checkout, and payment paths', 'speculation-pilot' ),
				'active' => true,
				'paths'  => array(
					'/cart/',
					'/cart/*',
					'/basket/',
					'/basket/*',
					'/checkout/',
					'/checkout/*',
					'/payment/*',
					'/pay/*',
					'/order-pay/*',
					'/order-received/*',
					'/thank-you/*',
					'/webhook/*',
					'/callback/*',
					'/callbacks/*',
				),
			),
			'query_strings'   => array(
				'label'  => __( 'Query-string actions', 'speculation-pilot' ),
				'active' => true,
				'paths'  => array(
					'/*\\?(.+)',
				),
			),
			'search'          => array(
				'label'  => __( 'Search pages', 'speculation-pilot' ),
				'active' => true,
				'paths'  => array(
					'/search/*',
					'/*\\?*s=*',
				),
			),
			'woocommerce'     => array(
				'label'  => 'WooCommerce',
				'active' => ! empty( $integrations['woocommerce'] ) || class_exists( 'WooCommerce' ),
				'paths'  => array(
					'/cart/',
					'/cart/*',
					'/checkout/',
					'/checkout/*',
					'/my-account/',
					'/my-account/*',
					'/wc-api/*',
					'/*\\?*add-to-cart*',
					'/*\\?*wc-ajax*',
					'/*/order-pay/*',
					'/*/order-received/*',
				),
			),
			'edd'             => array(
				'label'  => 'Easy Digital Downloads',
				'active' => ! empty( $integrations['edd'] ) || class_exists( 'Easy_Digital_Downloads' ),
				'paths'  => array(
					'/checkout/',
					'/checkout/*',
					'/purchase-confirmation/*',
					'/purchase-history/*',
					'/*\\?*edd_action*',
				),
			),
			'membership'      => array(
				'label'  => __( 'Membership and LMS paths', 'speculation-pilot' ),
				'active' => ! empty( $integrations['membership'] ) || ! empty( $integrations['lms'] ),
				'paths'  => array(
					'/account/*',
					'/dashboard/*',
					'/members/*',
					'/member/*',
					'/membership/*',
					'/memberships/*',
					'/subscriptions/*',
					'/groups/*',
					'/protected/*',
					'/course/*/checkout/*',
					'/courses/*/checkout/*',
					'/courses/*/enroll/*',
					'/lesson/*',
					'/lessons/*',
					'/quiz/*',
					'/quizzes/*',
					'/student-dashboard/*',
					'/my-courses/*',
				),
			),
			'multilingual'    => array(
				'label'  => __( 'Multilingual query actions', 'speculation-pilot' ),
				'active' => ! empty( $integrations['multilingual'] ) || defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'POLYLANG_VERSION' ) || defined( 'TRP_PLUGIN_VERSION' ),
				'paths'  => array(
					'/*\\?*lang=*',
					'/*\\?*wpml_lang=*',
					'/*\\?*trp-edit-translation=*',
				),
			),
			'funnel_checkout' => array(
				'label'  => __( 'Funnel and checkout builders', 'speculation-pilot' ),
				'active' => class_exists( 'WooFunnel_Loader' ) || defined( 'CARTFLOWS_FILE' ) || defined( 'FLUENT_CHECKOUT_VERSION' ),
				'paths'  => array(
					'/step/*',
					'/checkout-step/*',
					'/order-bump/*',
					'/upsell/*',
					'/downsell/*',
					'/*\\?*wfacp*',
					'/*\\?*wffn*',
				),
			),
			'cache_bypass'    => array(
				'label'  => __( 'Cache and optimization bypass URLs', 'speculation-pilot' ),
				'active' => ! empty( $integrations['cache'] ) || defined( 'WP_ROCKET_VERSION' ) || defined( 'LSCWP_V' ) || defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ),
				'paths'  => array(
					'/*\\?*nowprocket*',
					'/*\\?*ao_noptimize=*',
					'/*\\?*nocache=*',
					'/*\\?*litespeed*',
					'/*\\?*LSCWP_CTRL=*',
				),
			),
		);

		if ( 'balanced' === $preset || 'aggressive_lab' === $preset ) {
			$groups['search']['active'] = false;
		}

		if ( 'aggressive_lab' === $preset && 'prerender' !== $mode ) {
			$groups['query_strings']['active'] = false;
		}

		return $groups;
	}
}
