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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/session',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'create_embedded_account_session' ],
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
		$account = WC_Payments::get_account_service()->get_cached_account_data();
		if ( [] === $account ) {
			$default_currency = get_woocommerce_currency();
			$status           = WC_Payments_Account::is_on_boarding_disabled() ? 'ONBOARDING_DISABLED' : 'NOACCOUNT';
			$account          = [
				'card_present_eligible'    => false,
				'country'                  => WC()->countries->get_base_country(),
				'current_deadline'         => null,
				'has_overdue_requirements' => false,
				'has_pending_requirements' => false,
				'statement_descriptor'     => '',
				'status'                   => $status,
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

		if ( false !== $account ) {
			// Add extra properties to account if necessary.
			$account['card_present_eligible'] = false;
			$account['test_mode']             = WC_Payments::mode()->is_test();
			$account['test_mode_onboarding']  = WC_Payments::mode()->is_test_mode_onboarding();
		}

		return rest_ensure_response( $account );
	}

	/**
	 * Create an account embedded session via the API.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_embedded_account_session( WP_REST_Request $request ) {
		$account_session = WC_Payments::get_account_service()->create_embedded_account_session();

		if ( $account_session ) {
			$account_session['locale'] = get_user_locale();
		}

		return rest_ensure_response( $account_session );
	}
}
