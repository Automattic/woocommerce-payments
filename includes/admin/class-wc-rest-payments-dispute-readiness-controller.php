<?php
/**
 * Class WC_REST_Payments_Dispute_Readiness_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

use WCPay\Internal\Service\DisputeReadinessService;

/**
 * REST controller for dispute readiness functionality.
 */
class WC_REST_Payments_Dispute_Readiness_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/dispute-readiness';

	/**
	 * Dispute readiness service.
	 *
	 * @var DisputeReadinessService
	 */
	private $dispute_readiness_service;

	/**
	 * WC_REST_Payments_Dispute_Readiness_Controller constructor.
	 *
	 * @param WC_Payments_API_Client  $api_client WooPayments API client.
	 * @param DisputeReadinessService $service    Dispute readiness service.
	 */
	public function __construct( WC_Payments_API_Client $api_client, DisputeReadinessService $service ) {
		parent::__construct( $api_client );
		$this->dispute_readiness_service = $service;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_readiness' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/dismiss',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'dismiss_card' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/statement-descriptor/confirm',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'confirm_statement_descriptor' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve the dispute readiness overview payload.
	 *
	 * @return WP_REST_Response
	 */
	public function get_readiness() {
		if ( ! WC_Payments_Features::is_dispute_readiness_overview_enabled() ) {
			return rest_ensure_response( $this->dispute_readiness_service->get_disabled_overview_payload() );
		}

		return rest_ensure_response( $this->dispute_readiness_service->get_overview_payload() );
	}

	/**
	 * Dismiss the dispute readiness card.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function dismiss_card() {
		if ( ! WC_Payments_Features::is_dispute_readiness_overview_enabled() ) {
			return new WP_Error(
				'wcpay_dispute_readiness_disabled',
				__( 'Dispute readiness is disabled.', 'woocommerce-payments' ),
				[ 'status' => 403 ]
			);
		}

		return rest_ensure_response( $this->dispute_readiness_service->dismiss_overview_card() );
	}

	/**
	 * Confirm the current statement descriptor.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function confirm_statement_descriptor() {
		if ( ! WC_Payments_Features::is_dispute_readiness_overview_enabled() ) {
			return new WP_Error(
				'wcpay_dispute_readiness_disabled',
				__( 'Dispute readiness is disabled.', 'woocommerce-payments' ),
				[ 'status' => 403 ]
			);
		}

		return rest_ensure_response( $this->dispute_readiness_service->confirm_statement_descriptor() );
	}
}
