<?php
/**
 * Class WC_REST_Currency_Controller
 *
 * Backup for getting available currencies on LPM and MC onboarding screens, when the MC feature is disabled.
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\MultiCurrency\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for currencies.
 */
class WC_REST_Payments_Currency_Controller extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/multi-currency';

	/**
	 * Instance of WC_Payments_Account.
	 *
	 * @var WC_Payments_Account
	 */
	private $payments_account;

	/**
	 * WC_REST_Payments_Currency_Controller constructor.
	 *
	 * @param WC_Payments_Account $payments_account WC_Payments_Account instance.
	 */
	public function __construct( WC_Payments_Account $payments_account ) {
		$this->payments_account = $payments_account;
	}

	/**
	 * Verify access to request.
	 */
	public function check_permission() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/currencies',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_currencies' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}


	/**
	 * Returns the available currencies for the store.
	 *
	 * @return  array  Available currencies
	 */
	public function get_currencies() {
		return rest_ensure_response(
			[
				'available' => $this->get_available_currencies(),
				'enabled'   => [],
				'default'   => get_woocommerce_currency(),
			]
		);
	}

	/**
	 * Calculates the available currencies for the store and returns it.
	 *
	 * @return  array  The available currencies list.
	 */
	private function get_available_currencies() {
		// If Stripe is not connected, return an empty array. This prevents using MC without being connected to Stripe.
		if ( ! $this->payments_account->is_stripe_connected() ) {
			return [];
		}

		$result = [];

		// Add store currency with the rate "1.0".
		$woocommerce_currency            = get_woocommerce_currency();
		$result[ $woocommerce_currency ] = new Currency( $woocommerce_currency, 1.0 );

		$available_currencies = [];
		$wc_currencies        = array_keys( get_woocommerce_currencies() );
		$account_currencies   = $wc_currencies;
		$account              = $this->payments_account->get_cached_account_data();
		$supported_currencies = $this->payments_account->get_account_customer_supported_currencies();
		if ( $account && ! empty( $supported_currencies ) ) {
			$account_currencies = array_map( 'strtoupper', $supported_currencies );
		}

		$currencies = array_intersect( $account_currencies, $wc_currencies );

		foreach ( $currencies as $currency_code ) {
			$new_currency = new Currency( $currency_code, 1.0, null );

			// Add this to our list of available currencies.
			$available_currencies[ $new_currency->get_name() ] = $new_currency;
		}

		ksort( $available_currencies );

		foreach ( $available_currencies as $currency ) {
			$result[ $currency->get_code() ] = $currency;
		}

		return $result;
	}
}
