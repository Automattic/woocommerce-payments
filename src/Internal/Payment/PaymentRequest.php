<?php
/**
 * Class PaymentRequest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

/**
 * Class for loading, sanitizing, and escaping data from payment requests.
 */
class PaymentRequest {
	/**
	 * Request data.
	 *
	 * @var array
	 */
	private $request;

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
		return isset( $this->request['wcpay-fraud-prevention-token'] )
			? sanitize_text_field( $this->request['wcpay-fraud-prevention-token'] )
			: null;
	}

	/**
	 * Check if the request is a WooPay preflight check.
	 *
	 * @return bool
	 */
	public function is_woopay_preflight_check(): bool {
		return ! empty( $this->request['is-woopay-preflight-check'] );
	}

	/**
	 * Gets the provided WooPay intent ID from POST, if any.
	 *
	 * @return ?string
	 */
	public function get_woopay_intent_id(): ?string {
		return isset( $this->request['platform-checkout-intent'] )
			? sanitize_text_field( $this->request['platform-checkout-intent'] )
			: null;
	}

	/**
	 * Gets the ID of an order from the request.
	 *
	 * @return int|null
	 */
	public function get_order_id(): ?int {
		return isset( $this->request['order_id'] ) ? absint( $this->request['order_id'] ) : null;
	}

	/**
	 * Gets intent ID if any.
	 *
	 * @return string|null
	 */
	public function get_intent_id(): ?string {
		return isset( $this->request['intent_id'] )
			? sanitize_text_field( $this->request['intent_id'] )
			: null;
	}

	/**
	 * Gets the ID of the provided payment method.
	 *
	 * @return string|null
	 */
	public function get_payment_method_id(): ?string {
		return isset( $this->request['payment_method_id'] )
			? sanitize_text_field( $this->request['payment_method_id'] )
			: null;
	}
}
