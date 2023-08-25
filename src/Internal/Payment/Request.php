<?php
/**
 * Class Request
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

/**
 * Util class for loading, sanitizing, and escaping data from payment requests.
 */
class Request {

	/**
	 * Get the fraud prevention token from the request.
	 *
	 * @return string|null
	 */
	public static function get_fraud_prevention_token(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$res = $_POST['wcpay-fraud-prevention-token'] ?? null;
		return is_null( $res )
			? null
			: sanitize_key( $res );
	}

	/**
	 * Check if the request is a WooPay preflight check.
	 *
	 * @return bool
	 */
	public static function is_woopay_preflight_check(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return isset( $_POST['is-woopay-preflight-check'] );
	}
}
