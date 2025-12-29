<?php
/**
 * Class file for WCPay\Core\Server\Request\Get_PM_Promotions.
 *
 * @package WooCommerce Payments
 */

namespace WCPay\Core\Server\Request;

use WC_Payments_API_Client;
use WCPay\Core\Server\Request;

/**
 * GET WCPay payment method (PM) promotions from the Transact Platform.
 */
class Get_PM_Promotions extends Request {

	/**
	 * Specifies the WordPress hook name that will be triggered upon making the request.
	 *
	 * @var string
	 */
	protected $hook = 'wcpay_get_pm_promotions_request';

	/**
	 * Get API route.
	 *
	 * @return string
	 */
	public function get_api(): string {
		return WC_Payments_API_Client::PROMOTIONS_API;
	}

	/**
	 * Get method.
	 *
	 * @return string
	 */
	public function get_method(): string {
		return 'GET';
	}

	/**
	 * We want the raw response so we can look at the response headers.
	 *
	 * @return bool
	 */
	public function should_return_raw_response(): bool {
		return true;
	}

	/**
	 * Attaches store context details to the request.
	 *
	 * @param array $context The store context to send along with the request.
	 */
	public function set_store_context_params( array $context ): void {
		// Go through each context entry, validate, and set as param.
		foreach ( $context as $key => $value ) {
			// If the key is not a string, skip it.
			if ( ! is_string( $key ) ) {
				continue;
			}
			// If the value is null or empty, skip it.
			if ( is_null( $value ) || '' === $value ) {
				continue;
			}

			// JSON encode arrays (like dismissals) as the server expects strings.
			if ( is_array( $value ) ) {
				$encoded = wp_json_encode( $value );
				// Skip this parameter if encoding fails.
				if ( false === $encoded ) {
					continue;
				}
				$value = $encoded;
			}

			$this->set_param( $key, $value );
		}
	}
}
