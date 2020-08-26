<?php
/**
 * Class WC_Payment_Information
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Mostly a wrapper containing information on a single payment.
 */
class WC_Payment_Information {
	/**
	 * The ID of the payment method used for this payment.
	 *
	 * @var string
	 */
	private $payment_method;

	/**
	 * The payment token used for this payment.
	 *
	 * @var WC_Payment_Token/NULL
	 */
	private $token;

	/**
	 * Indicates whether this payment was made using a saved card.
	 *
	 * @var bool
	 */
	private $is_saved_method;

	/**
	 * Indicates whether the payment is merchant-initiated (true) or customer-initiated (false).
	 *
	 * @var bool
	 */
	private $off_session;

	/**
	 * Indicates whether this is a one-off payment (false) or the first installment of a recurring payment (true).
	 *
	 * @var bool
	 */
	private $is_recurring;

	/**
	 * Payment information constructor.
	 *
	 * @param string                $payment_method The ID of the payment method used for this payment.
	 * @param WC_Payment_Token/NULL $token The payment token used for this payment.
	 * @param array                 $is_saved_method Indicates whether this payment was made using a saved card.
	 * @param bool                  $is_recurring Indicates whether this is a one-off payment (false) or the first installment of a recurring payment (true).
	 * @param bool                  $off_session Indicates whether the payment is merchant-initiated (true) or customer-initiated (false).
	 *
	 * @throws Exception - If no payment method is found in the provided request.
	 */
	public function __construct( $payment_method, $token, $is_saved_method = false, $is_recurring = false, $off_session = false ) {
		$this->payment_method  = $payment_method;
		$this->token           = $token;
		$this->is_saved_method = $is_saved_method;
		$this->is_recurring    = $is_recurring;
		$this->off_session     = $off_session;
	}

	/**
	 * Returns true if payment was made with a saved card, false otherwise.
	 *
	 * @return bool True if payment was made with a saved card, false otherwise.
	 */
	public function is_using_saved_card() {
		return $this->is_saved_method;
	}

	/**
	 * Returns true if this payment is the first installment of a recurring payment,
	 * false otherwise.
	 *
	 * @return bool True if first installment of recurring payment, false otherwise.
	 */
	public function is_first_installment() {
		return $this->is_recurring;
	}

	/**
	 * Returns true if payment was initiated by the merchant, false otherwise.
	 *
	 * @return bool True if payment was initiated by the merchant, false otherwise.
	 */
	public function is_merchant_initiated() {
		return $this->is_recurring;
	}

	/**
	 * Returns the payment method ID.
	 *
	 * @return string The payment method ID.
	 */
	public function payment_method() {
		return $this->payment_method;
	}

	/**
	 * Returns the payment token.
	 *
	 * @return WC_Payment_Token/NULL The payment token.
	 */
	public function payment_token() {
		return $this->token;
	}

	/**
	 * Returns true if the payment token is not empty, false otherwise.
	 *
	 * @return bool True if payment token is not empty, false otherwise.
	 */
	public function has_payment_token() {
		return ! empty( $this->token );
	}

	/**
	 * Payment information constructor.
	 *
	 * @param array $request Associative array containing payment request information.
	 * @param bool  $is_recurring_payment Indicates whether this is a one-off payment (false) or the first installment of a recurring payment (true).
	 * @param bool  $off_session Indicates whether the payment is merchant-initiated (true) or customer-initiated (false).
	 *
	 * @throws Exception - If no payment method is found in the provided request.
	 */
	public static function from_payment_request( $request, $is_recurring_payment = false, $off_session = false ) {
		$payment_method  = self::get_payment_method_from_request( $request );
		$token           = self::get_token_from_request( $request );
		$is_saved_method = self::is_request_made_with_saved_method( $request );
		$is_recurring    = $is_recurring_payment;
		$off_session     = $off_session;

		return new WC_Payment_Information( $payment_method, $token, $is_saved_method, $is_recurring, $off_session );
	}

	/**
	 * Extracts information on whether the payment request was made with a saved card or not.
	 *
	 * @param array $request Associative array containing payment request information.
	 *
	 * @return bool True if payment was made with a saved card, false otherwise.
	 */
	public static function is_request_made_with_saved_method( $request ) {
		return empty( $request['wcpay-payment-method'] ) && ! empty( $request[ 'wc-' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token' ] );
	}

	/**
	 * Extracts the payment method from the provided request.
	 *
	 * @param array $request Associative array containing payment request information.
	 *
	 * @return string
	 * @throws Exception - If no payment method is found.
	 */
	public static function get_payment_method_from_request( $request ) {
		if ( empty( $request['wcpay-payment-method'] ) && empty( $request[ 'wc-' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token' ] ) ) {
			// If no payment method is set then stop here with an error.
			throw new Exception( __( 'Payment method not found.', 'woocommerce-payments' ) );
		}

		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$payment_method = ! empty( $request['wcpay-payment-method'] ) ? wc_clean( $request['wcpay-payment-method'] ) : null;

		if ( empty( $payment_method ) ) {
			$token = self::get_token_from_request( $request );

			if ( ! $token || WC_Payment_Gateway_WCPay::GATEWAY_ID !== $token->get_gateway_id() || $token->get_user_id() !== get_current_user_id() ) {
				throw new Exception( __( 'Invalid payment method. Please input a new card number.', 'woocommerce-payments' ) );
			}

			$payment_method = $token->get_token();
		}

		return $payment_method;
	}

	/**
	 * Extract the payment token from the provided request.
	 *
	 * @param array $request Associative array containing payment request information.
	 *
	 * @return WC_Payment_Token|NULL
	 */
	public static function get_token_from_request( $request ) {
		if ( ! isset( $request[ 'wc-' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token' ] ) ) {
			return null;
		}

		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		return WC_Payment_Tokens::get( wc_clean( $request[ 'wc-' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token' ] ) );
	}
}
