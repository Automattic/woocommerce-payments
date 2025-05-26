<?php
/**
 * Class WC_REST_Payments_Onboarding_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for account details and status.
 */
class WC_REST_Payments_Onboarding_Controller extends WC_Payments_REST_Controller {

	const RESULT_BAD_REQUEST = 'bad_request';

	/**
	 * Onboarding Service.
	 *
	 * @var WC_Payments_Onboarding_Service
	 */
	protected $onboarding_service;

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/onboarding';

	/**
	 * Constructor.
	 *
	 * @param WC_Payments_API_Client         $api_client         WooCommerce Payments API client.
	 * @param WC_Payments_Onboarding_Service $onboarding_service Onboarding Service class instance.
	 */
	public function __construct(
		WC_Payments_API_Client $api_client,
		WC_Payments_Onboarding_Service $onboarding_service
	) {
		parent::__construct( $api_client );
		$this->onboarding_service = $onboarding_service;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/kyc/session',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_embedded_kyc_session' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'progressive'     => [
						'required'    => false,
						'description' => 'Whether the session is for progressive onboarding.',
						// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
						// We expect a boolean (true, false, 0, 1, '0', '1', 'true', or 'false'), but will also accept `yes`/`no`.
						'type'        => [ 'boolean', 'string' ],
					],
					'self_assessment' => [
						'required'    => false,
						'description' => 'The self-assessment data.',
						'type'        => 'object',
						'properties'  => [
							'country'       => [
								'type'        => 'string',
								'description' => 'The country code where the company is legally registered.',
							],
							'business_type' => [
								'type'        => 'string',
								'description' => 'The company incorporation type.',
							],
							'mcc'           => [
								'type'        => 'string',
								'description' => 'The merchant category code. This can either be a true MCC or an MCCs tree item id from the onboarding form.',
							],
							'site'          => [
								'type'        => 'string',
								'description' => 'The URL of the site.',
							],
						],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/kyc/finalize',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'finalize_embedded_kyc' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'source' => [
						'required'    => false,
						'description' => 'The very first entry point the merchant entered our onboarding flow.',
						'type'        => 'string',
					],
					'from'   => [
						'required'    => false,
						'description' => 'The previous step in the onboarding flow leading the merchant to arrive at the current step.',
						'type'        => 'string',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reset',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reset_onboarding' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'source' => [
						'required'    => false,
						'description' => 'The very first entry point the merchant entered our onboarding flow.',
						'type'        => 'string',
					],
					'from'   => [
						'required'    => false,
						'description' => 'The previous step in the onboarding flow leading the merchant to arrive at the current step.',
						'type'        => 'string',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/fields',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_fields' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/business_types',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_business_types' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/test_drive_account/init',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'init_test_drive_account' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'country'      => [
						'type'        => 'string',
						'description' => 'The country code for which to create the test-drive account.',
						'required'    => false,
					],
					'capabilities' => [
						'description' => 'The capabilities to request and enable for the test-drive account. Leave empty to use the default capabilities.',
						'type'        => 'object',
						'default'     => [],
						'required'    => false,
						'properties'  => [
							'*' => [
								'type' => 'boolean',
							],
						],
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/test_drive_account/disable',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'disable_test_drive_account' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'source' => [
						'required'    => false,
						'description' => 'The very first entry point the merchant entered our onboarding flow.',
						'type'        => 'string',
					],
					'from'   => [
						'required'    => false,
						'description' => 'The previous step in the onboarding flow leading the merchant to arrive at the current step.',
						'type'        => 'string',
					],
				],
			]
		);
	}

	/**
	 * Create an account embedded KYC session via the API.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response Response object containing the account session, or an error if session creation failed.
	 */
	public function create_embedded_kyc_session( WP_REST_Request $request ) {
		$self_assessment_data = ! empty( $request->get_param( 'self_assessment' ) ) ? wc_clean( wp_unslash( $request->get_param( 'self_assessment' ) ) ) : [];
		$progressive          = ! empty( $request->get_param( 'progressive' ) ) && filter_var( $request->get_param( 'progressive' ), FILTER_VALIDATE_BOOLEAN );

		$account_session = $this->onboarding_service->create_embedded_kyc_session(
			$self_assessment_data,
			$progressive
		);

		if ( $account_session ) {
			$account_session['locale'] = get_user_locale();
		}

		return rest_ensure_response( $account_session );
	}

	/**
	 * Finalize the embedded KYC session via the API.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function finalize_embedded_kyc( WP_REST_Request $request ) {
		$source         = $request->get_param( 'source' ) ?? '';
		$from           = $request->get_param( 'from' ) ?? '';
		$actioned_notes = WC_Payments_Onboarding_Service::get_actioned_notes();

		// Call the API to finalize the onboarding.
		try {
			$response = $this->onboarding_service->finalize_embedded_kyc(
				get_user_locale(),
				$source,
				$actioned_notes
			);
		} catch ( Exception $e ) {
			return new WP_Error( self::RESULT_BAD_REQUEST, $e->getMessage(), [ 'status' => 400 ] );
		}

		// Handle some post-onboarding tasks and get the redirect params.
		$finalize = WC_Payments::get_account_service()->finalize_embedded_connection(
			$response['mode'],
			[
				'promo'  => $response['promotion_id'] ?? '',
				'from'   => $from,
				'source' => $source,
			]
		);

		// Return the response, the client will handle the redirect.
		return rest_ensure_response(
			array_merge(
				$response,
				$finalize
			)
		);
	}

	/**
	 * Reset the onboarding via the API.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function reset_onboarding( WP_REST_Request $request ) {
		$context = [
			'from'   => $request->get_param( 'from' ) ?? '',
			'source' => $request->get_param( 'source' ) ?? '',
		];

		try {
			$result = $this->onboarding_service->reset_onboarding( $context );
		} catch ( Exception $e ) {
			return new WP_Error( self::RESULT_BAD_REQUEST, $e->getMessage(), [ 'status' => 400 ] );
		}

		return rest_ensure_response( [ 'success' => $result ] );
	}

	/**
	 * Get fields data via API.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_fields( WP_REST_Request $request ) {
		$fields = $this->onboarding_service->get_fields_data( get_user_locale() );
		if ( is_null( $fields ) ) {
			return new WP_Error( self::RESULT_BAD_REQUEST, 'Failed to retrieve the onboarding fields.', [ 'status' => 400 ] );
		}

		return rest_ensure_response( [ 'data' => $fields ] );
	}

	/**
	 * Get business types via API.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_business_types( WP_REST_Request $request ) {
		$business_types = $this->onboarding_service->get_cached_business_types();
		return rest_ensure_response( [ 'data' => $business_types ] );
	}

	/**
	 * Initialize a test-drive account.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function init_test_drive_account( WP_REST_Request $request ) {
		$country = $request->get_param( 'country' );
		if ( empty( $country ) ) {
			// Fall back to the store's base country if no country is provided.
			$country = WC()->countries->get_base_country() ?? 'US';
		}

		try {
			$success = $this->onboarding_service->init_test_drive_account( $country, $request->get_param( 'capabilities' ) );
		} catch ( Exception $e ) {
			return new WP_Error( self::RESULT_BAD_REQUEST, $e->getMessage(), [ 'status' => 400 ] );
		}

		return rest_ensure_response(
			[
				'success' => $success,
			]
		);
	}

	/**
	 * Disable Test Drive account API.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function disable_test_drive_account( WP_REST_Request $request ) {
		$context = [
			'from'   => $request->get_param( 'from' ) ?? '',
			'source' => $request->get_param( 'source' ) ?? '',
		];

		try {
			$result = $this->onboarding_service->disable_test_drive_account( $context );
		} catch ( Exception $e ) {
			return new WP_Error( self::RESULT_BAD_REQUEST, $e->getMessage(), [ 'status' => 400 ] );
		}

		return rest_ensure_response( [ 'success' => $result ] );
	}
}
