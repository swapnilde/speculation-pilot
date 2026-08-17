<?php
/**
 * REST API.
 *
 * @package SpeculationPilot
 */

declare(strict_types=1);

namespace SpeculationPilot;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Registers REST routes.
 */
final class RestApi {
	private const NAMESPACE                  = 'speculation-pilot/v1';
	private const MAX_MEASUREMENT_BODY_BYTES = 4096;
	private const MEASUREMENT_KEYS           = array(
		'currentPath',
		'previousPath',
		'navigationType',
		'duration',
		'ttfb',
		'domInteractive',
		'loadComplete',
		'activationStart',
		'wasPrerender',
		'mode',
		'eagerness',
	);

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
	 * Diagnostics service.
	 *
	 * @var Diagnostics
	 */
	private $diagnostics;

	/**
	 * Measurements service.
	 *
	 * @var Measurements
	 */
	private $measurements;

	/**
	 * Constructor.
	 *
	 * @param Settings     $settings Settings service.
	 * @param SafetyEngine $safety Safety engine.
	 * @param Diagnostics  $diagnostics Diagnostics service.
	 * @param Measurements $measurements Measurements service.
	 */
	public function __construct( Settings $settings, SafetyEngine $safety, Diagnostics $diagnostics, Measurements $measurements ) {
		$this->settings     = $settings;
		$this->safety       = $safety;
		$this->diagnostics  = $diagnostics;
		$this->measurements = $measurements;
	}

	/**
	 * Registers REST routes.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers route definitions.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/diagnostics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_diagnostics' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/report',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_report' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/report/clear',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'clear_report' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/measurement',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'record_measurement' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/routes/suggestions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_route_suggestions' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Checks admin capability.
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Returns settings payload.
	 */
	public function get_settings(): WP_REST_Response {
		$is_pro = (bool) apply_filters( 'speculation_pilot_is_pro', false );

		return rest_ensure_response(
			array(
				'settings'       => $this->settings->get(),
				'exclusions'     => $this->safety->get_exclusion_paths(),
				'exclusionNotes' => $this->safety->get_exclusion_groups(),
				'isPro'          => $is_pro,
				'limits'         => array(
					'maxRetentionDays' => Settings::get_effective_retention_days_cap(),
					'maxExclusions'    => Settings::get_effective_max_exclusions(),
					'maxTopPaths'      => (int) apply_filters( 'speculation_pilot_max_top_paths', SPECULATION_PILOT_FREE_MAX_TOP_PATHS ),
					'csvEnabled'       => $is_pro,
				),
			)
		);
	}

	/**
	 * Updates settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$params   = $request->get_json_params();
		$params   = is_array( $params ) ? $params : array();
		$settings = $this->settings->update( $params );

		return rest_ensure_response(
			array(
				'settings'       => $settings,
				'exclusions'     => $this->safety->get_exclusion_paths(),
				'exclusionNotes' => $this->safety->get_exclusion_groups(),
			)
		);
	}

	/**
	 * Returns diagnostics.
	 */
	public function get_diagnostics(): WP_REST_Response {
		return rest_ensure_response( $this->diagnostics->get_report() );
	}

	/**
	 * Returns measurement report.
	 */
	public function get_report(): WP_REST_Response {
		return rest_ensure_response( $this->measurements->get_report() );
	}

	/**
	 * Clears local measurements.
	 */
	public function clear_report(): WP_REST_Response {
		return rest_ensure_response(
			array(
				'cleared' => $this->measurements->clear(),
				'report'  => $this->measurements->get_report(),
			)
		);
	}

	/**
	 * Records an anonymous measurement.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public function record_measurement( WP_REST_Request $request ): WP_REST_Response {
		$body = (string) $request->get_body();

		if ( strlen( $body ) > self::MAX_MEASUREMENT_BODY_BYTES ) {
			return new WP_REST_Response(
				array(
					'accepted' => false,
					'reason'   => 'payload_too_large',
				),
				413
			);
		}

		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			return new WP_REST_Response(
				array(
					'accepted' => false,
					'reason'   => 'malformed_json',
				),
				400
			);
		}

		$unexpected_keys = array_diff( array_keys( $params ), self::MEASUREMENT_KEYS );
		if ( ! empty( $unexpected_keys ) ) {
			return new WP_REST_Response(
				array(
					'accepted'       => false,
					'reason'         => 'unexpected_fields',
					'unexpectedKeys' => array_values( $unexpected_keys ),
				),
				400
			);
		}

		$result = $this->measurements->record( $params );
		$status = ! empty( $result['accepted'] ) ? 202 : $this->get_measurement_error_status( (string) $result['reason'] );

		return new WP_REST_Response( $result, $status );
	}

	/**
	 * Maps measurement failure reasons to HTTP statuses.
	 *
	 * @param string $reason Failure reason.
	 */
	private function get_measurement_error_status( string $reason ): int {
		if ( 'rate_limited' === $reason ) {
			return 429;
		}

		if ( 'measurement_disabled' === $reason ) {
			return 202;
		}

		if ( 0 === strpos( $reason, 'invalid_' ) ) {
			return 400;
		}

		if ( 'db_insert_failed' === $reason ) {
			return 500;
		}

		return 400;
	}

	/**
	 * Returns suggested exclusion path patterns.
	 */
	public function get_route_suggestions(): WP_REST_Response {
		$suggestions = array(
			array(
				'path'  => '/cart/*',
				'label' => __( 'Cart Pages', 'speculation-pilot' ),
			),
			array(
				'path'  => '/checkout/*',
				'label' => __( 'Checkout Pages', 'speculation-pilot' ),
			),
			array(
				'path'  => '/my-account/*',
				'label' => __( 'Account Pages', 'speculation-pilot' ),
			),
			array(
				'path'  => '/login/*',
				'label' => __( 'Login Routes', 'speculation-pilot' ),
			),
			array(
				'path'  => '/search/*',
				'label' => __( 'Search Pages', 'speculation-pilot' ),
			),
			array(
				'path'  => '/*\?*s=*',
				'label' => __( 'Search Query Strings', 'speculation-pilot' ),
			),
			array(
				'path'  => '/*\?*add-to-cart*',
				'label' => __( 'Add-To-Cart Links', 'speculation-pilot' ),
			),
		);

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $post_types as $pt ) {
			if ( ! empty( $pt->rewrite['slug'] ) && 'post' !== $pt->name && 'page' !== $pt->name ) {
				$suggestions[] = array(
					'path'  => '/' . trim( (string) $pt->rewrite['slug'], '/' ) . '/*',
					'label' => sprintf( __( '%s Archive Paths', 'speculation-pilot' ), $pt->labels->singular_name ),
				);
			}
		}

		return rest_ensure_response( array( 'suggestions' => $suggestions ) );
	}
}
