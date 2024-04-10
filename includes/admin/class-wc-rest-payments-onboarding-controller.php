<?php
/**
 * Class WC_REST_Payments_Onboarding_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Exceptions\API_Exception;
use WCPay\Exceptions\Rest_Request_Exception;

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
			'/' . $this->rest_base . '/business_types',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_business_types' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/required_verification_information',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_required_verification_information' ],
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
			],
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
	 * Get required verification information via API.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 *
	 * @throws Rest_Request_Exception
	 */
	public function get_required_verification_information( WP_REST_Request $request ) {
		$country_code = $request->get_param( 'country' ) ?? null;
		$type         = $request->get_param( 'type' ) ?? null;
		$structure    = $request->get_param( 'structure' ) ?? null;

		try {
			if ( ! $country_code || ! $type ) {
				throw new Rest_Request_Exception( __( 'Country or type parameter was missing', 'woocommerce-payments' ) );
			}

			$verification_info = $this->onboarding_service->get_required_verification_information( $country_code, $type, $structure );

			return rest_ensure_response(
				[
					'data' => $verification_info,
				]
			);
		} catch ( Rest_Request_Exception $e ) {
			return new WP_REST_Response( [ 'result' => self::RESULT_BAD_REQUEST ], 400 );
		} catch ( API_Exception $e ) {
			return new WP_Error( $e->get_error_code(), $e->getMessage() );
		}
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
