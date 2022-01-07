<?php
/**
 * Class WC_Payments_Subscription_Minimum_Amount_Handler
 *
 * @package WooCommerce\Payments
 */

/**
 * The WC_Payments_Subscription_Minimum_Amount_Handler class
 */
class WC_Payments_Subscription_Minimum_Amount_Handler {

	/**
	 * The API client object.
	 *
	 * @var WC_Payments_API_Client
	 */
	private $api_client;

	/**
	 * The transient key used to store the minimum amounts for a given currency.
	 *
	 * @const string
	 */
	const MINIMUM_RECURRING_AMOUNT_TRANSIENT_KEY = 'wcpay_subscription_minimum_recurring_amounts';

	/**
	 * The length of time in seconds the minimum amount is stored in a transient.
	 *
	 * @const int
	 */
	const MINIMUM_RECURRING_AMOUNTS_TRANSIENT_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Initialize the class.
	 *
	 * @param WC_Payments_API_Client $api_client The API client object.
	 */
	public function __construct( WC_Payments_API_Client $api_client ) {
		$this->api_client = $api_client;
		add_filter( 'woocommerce_subscriptions_minimum_processable_recurring_amount', [ $this, 'get_minimum_recurring_amount' ], 10, 2 );
	}

	/**
	 * Gets the minimum WC Pay Subscription recurring amount that can be transacted in a given currency.
	 *
	 * @param int|bool $minimum_amount The minimum amount that can be processed in recurring transactions. Can be an int (the minimum amount) or false if no minimum exists.
	 * @param string   $currency_code  The currency to fetch the minimum amount in.
	 *
	 * @return int The minimum recurring amount.
	 */
	public function get_minimum_recurring_amount( $minimum_amount, $currency_code ) {
		$transient_key  = self::MINIMUM_RECURRING_AMOUNT_TRANSIENT_KEY . "_$currency_code";
		$minimum_amount = get_transient( $transient_key );

		if ( false === $minimum_amount ) {
			$minimum_amount = $this->api_client->get_currency_minimum_recurring_amount( $currency_code );
			set_transient( $transient_key, $minimum_amount, self::MINIMUM_RECURRING_AMOUNTS_TRANSIENT_EXPIRATION );
		}

		return WC_Payments_Utils::interpret_stripe_amount( $minimum_amount, strtolower( $currency_code ) );
	}
}
