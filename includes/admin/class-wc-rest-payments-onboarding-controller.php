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
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_embedded_kyc_session' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'progressive'     => [
						'required'    => false,
						'description' => 'Whether the session is for progressive onboarding.',
						'type'        => 'string',
					],
					'self_assessment' => [
						'required'    => false,
						'description' => 'The self-assessment data.',
						'type'        => 'object',
						'properties'  => [
							'country'           => [
								'type'        => 'string',
								'description' => 'The country code where the company is legally registered.',
								'required'    => true,
							],
							'business_type'     => [
								'type'        => 'string',
								'description' => 'The company incorporation type.',
								'required'    => true,
							],
							'mcc'               => [
								'type'        => 'string',
								'description' => 'The merchant category code. This can either be a true MCC or an MCCs tree item id from the onboarding form.',
								'required'    => true,
							],
							'annual_revenue'    => [
								'type'        => 'string',
								'description' => 'The estimated annual revenue bucket id.',
								'required'    => true,
							],
							'go_live_timeframe' => [
								'type'        => 'string',
								'description' => 'The timeframe bucket for the estimated first live transaction.',
								'required'    => true,
							],
							'url'               => [
								'type'        => 'string',
								'description' => 'The URL of the store.',
								'required'    => true,
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
			'/' . $this->rest_base . '/business_types',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_business_types' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/router/po_eligible',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'args'                => [
					'business'        => [
						'required'    => true,
						'description' => 'The context about the merchant\'s business (self-assessment data).',
						'type'        => 'object',
						'properties'  => [
							'country' => [
								'type'        => 'string',
								'description' => 'The country code where the company is legally registered.',
								'required'    => true,
							],
							'type'    => [
								'type'        => 'string',
								'description' => 'The company incorporation type.',
								'required'    => true,
							],
							'mcc'     => [
								'type'        => 'string',
								'description' => 'The merchant category code. This can either be a true MCC or an MCCs tree item id from the onboarding form.',
								'required'    => true,
							],
						],
					],
					'store'           => [
						'required'    => true,
						'description' => 'The context about the merchant\'s store (self-assessment data).',
						'type'        => 'object',
						'properties'  => [
							'annual_revenue'    => [
								'type'        => 'string',
								'description' => 'The estimated annual revenue bucket id.',
								'required'    => true,
							],
							'go_live_timeframe' => [
								'type'        => 'string',
								'description' => 'The timeframe bucket for the estimated first live transaction.',
								'required'    => true,
							],
						],
					],
					'woo_store_stats' => [
						'required'    => false,
						'description' => 'Context about the merchant\'s current WooCommerce store.',
						'type'        => 'object',
					],
				],
				'callback'            => [ $this, 'get_progressive_onboarding_eligible' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Create an account embedded KYC session via the API.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_embedded_kyc_session( WP_REST_Request $request ) {
		$self_assessment_data = ! empty( $request->get_param( 'self_assessment' ) ) ? wc_clean( wp_unslash( $request->get_param( 'self_assessment' ) ) ) : [];
		$progressive          = ! empty( $request->get_param( 'progressive' ) ) && 'true' === $request->get_param( 'progressive' );

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
	 * Get progressive onboarding eligibility via API.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_progressive_onboarding_eligible( WP_REST_Request $request ) {
		return $this->forward_request(
			'get_onboarding_po_eligible',
			[
				'business_info'   => $request->get_param( 'business' ),
				'store_info'      => $request->get_param( 'store' ),
				'woo_store_stats' => $request->get_param( 'woo_store_stats' ) ?? [],
			]
		);
	}
}
