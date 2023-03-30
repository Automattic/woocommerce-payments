<?php
/**
 * Class Verify_Minimum_Amount_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use WC_Payments_Utils;
use WCPay\Exceptions\Amount_Too_Small_Exception;
use WCPay\Payment_Process\Order_Payment;
use WCPay\Payment_Process\Payment;

/**
 * Checks if an order matches the required minimum amount.
 */
final class Verify_Minimum_Amount_Step extends Abstract_Step {
	/**
	 * Checks if the step is applicable.
	 *
	 * @param Payment $payment A payment, which is being processed.
	 * @return bool
	 */
	public function is_applicable( Payment $payment ) {
		return is_a( $payment, Order_Payment::class );
	}

	/**
	 * Verifies the amount during the processing step.
	 * Tries catching the error without reaching the API.
	 *
	 * @param Payment $payment The payment, which is being processed.
	 * @throws Amount_Too_Small_Exception
	 */
	public function action( Payment $payment ) {
		if ( ! $payment instanceof Order_Payment ) {
			return;
		}

		$order          = $payment->get_order();
		$currency       = strtolower( $order->get_currency() );
		$minimum_amount = WC_Payments_Utils::get_cached_minimum_amount( $currency );
		if ( is_null( $minimum_amount ) ) {
			// If there is no cached minimum amount already, there is nothing to catch.
			return;
		}

		$amount           = $order->get_total();
		$converted_amount = WC_Payments_Utils::prepare_amount( $amount, $order->get_currency() );

		if ( $minimum_amount > $converted_amount ) {
			throw new Amount_Too_Small_Exception( 'Amount too small', $minimum_amount, $currency, 400 );
		}
	}
}
