<?php
/**
 * Class Payment_Information
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\DataTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Mostly a wrapper containing information on a single payment.
 */
class Payment_Information {
	/**
	 * The ID of the payment method used for this payment.
	 *
	 * @var string
	 */
	private $payment_method;

	/**
	 * The payment token used for this payment.
	 *
	 * @var \WC_Payment_Token/NULL
	 */
	private $token;

	/**
	 * Indicates whether the payment is merchant-initiated (true) or customer-initiated (false).
	 *
	 * @var bool
	 */
	private $off_session;

	/**
	 * Payment information constructor.
	 *
	 * @param string            $payment_method The ID of the payment method used for this payment.
	 * @param \WC_Payment_Token $token The payment token used for this payment.
	 * @param bool              $off_session Indicates whether the payment is merchant-initiated (true) or customer-initiated (false).
	 *
	 * @throws \Exception - If no payment method is found in the provided request.
	 */
	public function __construct(
		string $payment_method,
		\WC_Payment_Token $token = null,
		bool $off_session = false
	) {
		if ( empty( $payment_method ) && empty( $token ) ) {
			throw new \Exception( __( 'Invalid payment method. Please input a new card number.', 'woocommerce-payments' ) );
		}

		$this->payment_method = $payment_method;
		$this->token          = $token;
		$this->off_session    = $off_session;
	}

	/**
	 * Returns true if payment was initiated by the merchant, false otherwise.
	 *
	 * @return bool True if payment was initiated by the merchant, false otherwise.
	 */
	public function is_merchant_initiated(): bool {
		return $this->off_session;
	}

	/**
	 * Returns the payment method ID.
	 *
	 * @return string The payment method ID.
	 */
	public function get_payment_method(): string {
		// Use the token if we have it.
		if ( $this->is_using_saved_payment_method() ) {
			return $this->token->get_token();
		}

		return $this->payment_method;
	}

	/**
	 * Returns the payment token.
	 *
	 * TODO: Once php requirement is bumped to >= 7.1.0 change return type to ?\WC_Payment_Token
	 * since the return type is nullable, as per
	 * https://www.php.net/manual/en/functions.returning-values.php#functions.returning-values.type-declaration
	 *
	 * @return \WC_Payment_Token/NULL The payment token.
	 */
	public function get_payment_token(): \WC_Payment_Token {
		return $this->token;
	}

	/**
	 * Update the payment token associated with this payment.
	 *
	 * @param \WC_Payment_Token $token The new payment token.
	 */
	public function set_token( \WC_Payment_Token $token ) {
		$this->token = $token;
	}

	/**
	 * Returns true if the payment token is not empty, false otherwise.
	 *
	 * @return bool True if payment token is not empty, false otherwise.
	 */
	public function is_using_saved_payment_method(): bool {
		return ! empty( $this->token );
	}

	/**
	 * Payment information constructor.
	 *
	 * @param array $request Associative array containing payment request information.
	 * @param bool  $off_session Indicates whether the payment is merchant-initiated (true) or customer-initiated (false).
	 *
	 * @throws Exception - If no payment method is found in the provided request.
	 */
	public static function from_payment_request(
		array $request,
		bool $off_session = false
	): Payment_Information {
		$payment_method = self::get_payment_method_from_request( $request );
		$token          = self::get_token_from_request( $request );
		$off_session    = $off_session;

		return new Payment_Information( $payment_method, $token, $off_session );
	}

	/**
	 * Extracts the payment method from the provided request.
	 *
	 * @param array $request Associative array containing payment request information.
	 *
	 * @return string
	 */
	public static function get_payment_method_from_request( array $request ): string {
		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		return ! empty( $request['wcpay-payment-method'] ) ? wc_clean( $request['wcpay-payment-method'] ) : '';
	}

	/**
	 * Extract the payment token from the provided request.
	 *
	 * TODO: Once php requirement is bumped to >= 7.1.0 set return type to ?\WC_Payment_Token
	 * since the return type is nullable, as per
	 * https://www.php.net/manual/en/functions.returning-values.php#functions.returning-values.type-declaration
	 *
	 * @param array $request Associative array containing payment request information.
	 *
	 * @return \WC_Payment_Token|NULL
	 */
	public static function get_token_from_request( array $request ) {
		if (
			! isset( $request[ 'wc-' . \WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token' ] ) ||
			'new' === $request[ 'wc-' . \WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token' ]
		) {
			return null;
		}

		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$token = \WC_Payment_Tokens::get( wc_clean( $request[ 'wc-' . \WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token' ] ) );

		// If the token doesn't belong to this gateway or the current user it's invalid.
		if ( ! $token || \WC_Payment_Gateway_WCPay::GATEWAY_ID !== $token->get_gateway_id() || $token->get_user_id() !== get_current_user_id() ) {
			return null;
		}

		return $token;
	}
}
