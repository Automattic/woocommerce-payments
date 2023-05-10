<?php
/**
 * Class Payment_Method_Factory
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Payment_Method;

use WC_Payment_Tokens;
use WC_Payment_Gateway_WCPay;
use Exception;

/**
 * A class for generating payment methods for the payment process.
 */
class Payment_Method_Factory {
	/**
	 * The input name when entering a new payment method.
	 */
	const NEW_PAYMENT_METHOD_INPUT = 'wcpay-payment-method';

	/**
	 * Retrieves the payment method from a request.
	 *
	 * @param array $request    The request object, normally equal to $_POST.
	 * @param bool  $ignore_new Sometimes this should just check for a saved payment method.
	 * @return Payment_Method|null
	 * @throws Exception If something smeels.
	 */
	public function from_request( array $request, bool $ignore_new = false ) {
		// Check if WCPay is the chosen gateway.
		if ( ! isset( $request['payment_method'] ) || WC_Payment_Gateway_WCPay::GATEWAY_ID !== $request['payment_method'] ) {
			throw new Exception( 'WooCommerce Payments is not used during checkout, cannot retrieve payment method.' );
		}

		// Look for a token first. ToDo: This might not work with blocks.
		$token_name = 'wc-' . WC_Payment_Gateway_WCPay::GATEWAY_ID . '-payment-token';
		$token_id   = isset( $request[ $token_name ] ) ? $request[ $token_name ] : 'new';

		if ( 'new' === $token_id ) {
			// In some cases, ex. UPE, a new payment method might be chosen, but not available in the request.
			if ( $ignore_new ) {
				return null;
			}

			// There must be a new payment method entered.
			if ( empty( $request[ self::NEW_PAYMENT_METHOD_INPUT ] ) ) {
				throw new Exception( 'A new payment method was selected, but not entered.' );
			}

			$normalized = wc_clean( $request[ self::NEW_PAYMENT_METHOD_INPUT ] );
			if ( ! is_string( $normalized ) || empty( $normalized ) ) {
				throw new Exception( 'Invalid new payment method ID.' );
			}

			return new New_Payment_Method( $normalized );
		}

		// At this stage the saved payment method will be used.
		$token = WC_Payment_Tokens::get( wc_clean( $token_id ) );

		if (
			! $token // Not existing.
			|| WC_Payment_Gateway_WCPay::GATEWAY_ID !== $token->get_gateway_id() // Wrong gateway.
			|| $token->get_user_id() !== get_current_user_id() // Wrong user.
		) {
			throw new Exception( 'The selected saved payment method either does not exist, belongs to the wrong gateway or customer.' );
		}

		return new Saved_Payment_Method( $token );
	}

	/**
	 * Generates the payment method object from a saved array.
	 *
	 * @param array $data The saved data of the payment method.
	 * @return Payment_Method
	 * @throws Exception If the payment method could not be loaded.
	 */
	public function from_storage( array $data ) {
		if ( 'new' === $data['type'] ) {
			return new New_Payment_Method( $data['id'] );
		}

		if ( 'saved' === $data['type'] ) {
			return new Saved_Payment_Method( WC_Payment_Tokens::get( $data['id'] ) );
		}

		throw new Exception( 'Unknown stored payment method type' );
	}
}
