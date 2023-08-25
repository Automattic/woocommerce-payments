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
	 * The request array.
	 *
	 * @param  array|null $request  Request data, this can be $_POST, or WP_REST_Request::get_params().
	 */
	public function __construct( array $request = null ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$this->request = $request ?? $_POST;
	}

	/**
	 * Get the fraud prevention token from the request.
	 *
	 * @return string|null
	 */
	public function get_fraud_prevention_token(): ?string {
		$res = $this->request['wcpay-fraud-prevention-token'] ?? null;
		return is_null( $res )
			? null
			: sanitize_key( $res );
	}

	/**
	 * Check if the request is a WooPay preflight check.
	 *
	 * @return bool
	 */
	public function is_woopay_preflight_check(): bool {
		return isset( $this->request['is-woopay-preflight-check'] );
	}
}
