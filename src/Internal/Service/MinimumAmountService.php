<?php
/**
 * MinimumAmountService class.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Payments_Utils;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Minimum Amount Service to handle errors from the server when the payment amount is too small.
 */
class MinimumAmountService {
	/**
	 * Transient key prefix.
	 */
	private const TRANSIENT_KEY = 'wcpay_minimum_amount_';

	/**
	 * Legacy Proxy.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * Constructor.
	 *
	 * @param LegacyProxy $legacy_proxy Legacy proxy instance.
	 */
	public function __construct( LegacyProxy $legacy_proxy ) {
		$this->legacy_proxy = $legacy_proxy;
	}

	/**
	 * Saves the minimum amount required for transactions in a given currency.
	 *
	 * @param string $currency The currency.
	 * @param int    $amount   The minimum amount.
	 */
	public function set_cache( string $currency, int $amount ): void {
		$key = self::TRANSIENT_KEY . strtolower( $currency );
		$this->legacy_proxy->call_function( 'set_transient', $key, $amount, DAY_IN_SECONDS );
	}

	/**
	 * Checks if there is a minimum amount required for transactions in a given currency.
	 *
	 * @param string $currency The currency to check for.
	 *
	 * @return int The minimum amount. 0 if the cache has not been set, or it is an invalid value.
	 */
	public function get_cache( string $currency ): int {
		$key    = self::TRANSIENT_KEY . strtolower( $currency );
		$cached = $this->legacy_proxy->call_function( 'get_transient', $key );

		return (int) $cached;
	}

	/**
	 * Gets the error message for shoppers.
	 *
	 * @param string $currency       The currency.
	 * @param int    $minimum_amount The minimum amount.
	 *
	 * @return string Error message relayed to shoppers.
	 */
	public function get_error_message_for_shoppers( string $currency, int $minimum_amount ): string {
		$interpreted_amount = $this->legacy_proxy->call_static(
			WC_Payments_Utils::class,
			'interpret_stripe_amount',
			$minimum_amount,
			$currency
		);
		$formatted_amount   = $this->legacy_proxy->call_static(
			WC_Payments_Utils::class,
			'format_currency',
			$interpreted_amount,
			$currency
		);

		return sprintf(
			// translators: %s a formatted amount with currency, such as 0.5$.
			__(
				'The selected payment method requires a total amount of at least %s.',
				'woocommerce-payments'
			),
			$formatted_amount
		);
	}
}
