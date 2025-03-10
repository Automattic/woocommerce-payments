<?php
/**
 * Class PaymentRequest
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WC_Payment_Gateway_WCPay;
use WC_Payment_Token;
use WC_Payment_Tokens;
use WCPay\Internal\Payment\PaymentMethod\NewPaymentMethod;
use WCPay\Internal\Payment\PaymentMethod\PaymentMethodInterface;
use WCPay\Internal\Payment\PaymentMethod\SavedPaymentMethod;
use WCPay\Internal\Proxy\LegacyProxy;

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
	 * Legacy proxy.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * Extract information from request data.
	 *
	 * @param  LegacyProxy $legacy_proxy Legacy proxy.
	 * @param  array|null  $request      Request data, this can be $_POST, or WP_REST_Request::get_params().
	 */
	public function __construct( LegacyProxy $legacy_proxy, ?array $request = null ) {
		$this->legacy_proxy = $legacy_proxy;
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
			? sanitize_text_field( wp_unslash( ( $this->request['wcpay-fraud-prevention-token'] ) ) )
			: null;
	}

	/**
	 * Check if the request is a WooPay preflight check.
	 *
	 * @return bool
	 */
	public function is_woopay_preflight_check(): bool {
		return isset( $this->request['is-woopay-preflight-check'] );
	}

	/**
	 * Gets the provided WooPay intent ID from POST, if any.
	 *
	 * @return ?string
	 */
	public function get_woopay_intent_id(): ?string {
		return isset( $this->request['platform-checkout-intent'] )
			? sanitize_text_field( wp_unslash( ( $this->request['platform-checkout-intent'] ) ) )
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
			? sanitize_text_field( wp_unslash( ( $this->request['intent_id'] ) ) )
			: null;
	}

	/**
	 * Gets the ID of the provided payment method.
	 *
	 * @return string|null
	 */
	public function get_payment_method_id(): ?string {
		return isset( $this->request['payment_method_id'] )
			? sanitize_text_field( wp_unslash( ( $this->request['payment_method_id'] ) ) )
			: null;
	}

	/**
	 * Gets payment method object from request.
	 *
	 * @throws PaymentRequestException
	 */
	public function get_payment_method(): PaymentMethodInterface {
		$request = $this->request;

		$is_woopayment_selected = isset( $request['payment_method'] ) && WC_Payment_Gateway_WCPay::GATEWAY_ID === $request['payment_method'];
		if ( ! $is_woopayment_selected ) {
			throw new PaymentRequestException( esc_html__( 'WooPayments is not used during checkout.', 'woocommerce-payments' ) );
		}

		$token_request_key = 'wc-' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token';
		if ( isset( $request[ $token_request_key ] ) && 'new' !== $request[ $token_request_key ] ) {
			$token_id = absint( wp_unslash( $request [ $token_request_key ] ) );

			/**
			 * Retrieved token object.
			 *
			 * @var null| WC_Payment_Token $token
			 */
			$token = $this->legacy_proxy->call_static( WC_Payment_Tokens::class, 'get', $token_id );

			if ( is_null( $token ) ) {
				throw new PaymentRequestException( esc_html__( 'Invalid saved payment method (token) ID.', 'woocommerce-payments' ) );
			}
			return new SavedPaymentMethod( $token->get_token(), $token->get_id() );
		}

		if ( ! empty( $request['wcpay-payment-method'] ) ) {
			$payment_method = sanitize_text_field( wp_unslash( $request['wcpay-payment-method'] ) );
			return new NewPaymentMethod( $payment_method );
		}

		throw new PaymentRequestException( esc_html__( 'No valid payment method was selected.', 'woocommerce-payments' ) );
	}

	/**
	 * Extract the payment CVC confirmation from the request.
	 *
	 * @return string|null
	 */
	public function get_cvc_confirmation(): ?string {
		$payment_method = $this->request['payment_method'] ?? null;
		if ( null === $payment_method ) {
			return null;
		}

		$cvc_request_key = 'wc-' . $payment_method . '-payment-cvc-confirmation';
		if (
			! isset( $this->request[ $cvc_request_key ] ) ||
			'new' === $this->request[ $cvc_request_key ]
		) {
			return null;
		}

		return $this->request[ $cvc_request_key ];
	}

	/**
	 * Extracts the fingerprint data from the request.
	 *
	 * @return string
	 */
	public function get_fingerprint(): ?string {
		if ( ! empty( $this->request['wcpay-fingerprint'] ) ) {
			$normalized = wc_clean( $this->request['wcpay-fingerprint'] );
			if ( is_string( $normalized ) ) {
				return $normalized;
			}
		}

		return null;
	}
}
