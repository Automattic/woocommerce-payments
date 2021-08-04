<?php
/**
 * Class WC_REST_Payments_Accounts_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for account details and status.
 */
class WC_REST_Payments_Accounts_Controller extends WC_Payments_REST_Controller {

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/accounts';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/payments/accounts',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_account_data' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Get account details via API.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_account_data( $request ) {
		$account_service = WC_Payments::get_account_service()->with_payments_api_client( $this->api_client );
		$account         = $account_service->get_cached_account_data();
		if ( [] === $account ) {
			$default_currency = get_woocommerce_currency();
			$account          = [
				'card_present_eligible'    => false,
				'country'                  => WC()->countries->get_base_country(),
				'current_deadline'         => null,
				'has_overdue_requirements' => false,
				'has_pending_requirements' => false,
				'statement_descriptor'     => '',
				'status'                   => 'NOACCOUNT',
				'store_currencies'         => [
					'default'   => $default_currency,
					'supported' => [
						$default_currency,
					],
				],
				'customer_currencies'      => [
					'supported' => [
						$default_currency,
					],
				],
			];
		}

		return rest_ensure_response( $account );
	}
}
