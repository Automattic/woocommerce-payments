<?php
/**
 * MinimumAmountService class.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WCPay\Exceptions\Amount_Too_Small_Exception;
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
	 * Extracts and stores the amount, provided by the API through an exception.
	 *
	 * @param Amount_Too_Small_Exception $exception The exception that was thrown.
	 */
	public function store_amount_from_exception( Amount_Too_Small_Exception $exception ): void {
		$this->set_cached_amount(
			$exception->get_currency(),
			$exception->get_minimum_amount()
		);
	}

	/**
	 * Checks if there is a minimum amount required for transactions in a given currency.
	 *
	 * @param string $currency Currency to check.
	 * @param int    $amount   Amount in cents to check.
	 *
	 * @throws Amount_Too_Small_Exception
	 */
	public function verify_amount( string $currency, int $amount ): void {
		$minimum_amount = $this->get_cached_amount( $currency );

		if ( $minimum_amount > $amount ) {
			throw new Amount_Too_Small_Exception( __( 'Order amount too small', 'woocommerce-payments' ), $minimum_amount, $currency, 400 );
		}
	}

	/**
	 * Saves the minimum amount required for transactions in a given currency.
	 *
	 * @param string $currency The currency.
	 * @param int    $amount   The minimum amount in cents.
	 */
	private function set_cached_amount( string $currency, int $amount ): void {
		$key = self::TRANSIENT_KEY . strtolower( $currency );
		$this->legacy_proxy->call_function( 'set_transient', $key, $amount, DAY_IN_SECONDS );
	}

	/**
	 * Checks if there is a minimum amount required for transactions in a given currency.
	 *
	 * @param string $currency The currency to check for.
	 *
	 * @return int The minimum amount in cents. 0 if the cache has not been set, or it is an invalid value.
	 */
	private function get_cached_amount( string $currency ): int {
		$key    = self::TRANSIENT_KEY . strtolower( $currency );
		$cached = $this->legacy_proxy->call_function( 'get_transient', $key );

		return (int) $cached;
	}
}
