<?php
/**
 * Class Bump_Transaction_Limiter_Step
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment_Process\Step;

use Exception;
use WC_Payments;
use WCPay\Payment_Process\Payment;
use WCPay\Session_Rate_Limiter;

/**
 * Bumps the transaction limiter for `Check_Failed_Transactions_Limit_Step`.
 */
final class Bump_Transaction_Limiter_Step extends Abstract_Step {
	/**
	 * Session_Rate_Limiter instance for limiting failed transactions.
	 *
	 * @var Session_Rate_Limiter
	 */
	protected $failed_transaction_rate_limiter;

	/**
	 * Gathers all required dependencies.
	 */
	public function __construct() {
		$this->failed_transaction_rate_limiter = WC_Payments::get_transaction_rate_limiter();
	}

	/**
	 * Before other significant actions are performed, this will
	 * check the transaction rate limiter.
	 *
	 * @param Payment $payment Payment process.
	 * @throws Exception Whenever the limiter is hit, and the process cannot continue.
	 */
	public function action( Payment $payment ) {
		if ( $this->failed_transaction_rate_limiter->is_limited() ) {
			throw new Exception( __( 'Your payment was not processed.', 'woocommerce-payments' ) );
		}
	}

	/**
	 * During the completion stage of the payment process, bumps the limiter if necessary.
	 *
	 * @param Payment $payment Payment proccess.
	 */
	public function complete( Payment $payment ) {
		// @todo: This error code might come from an exception in the main gateway, not just the intent?
		// UPE method gives us the error of the previous payment attempt, so we use that for the Rate Limiter.
		$intent     = $payment->get_intent();
		$error_code = $intent->get_last_payment_error()['code'] ?? '';

		if ( $this->should_bump_rate_limiter( $error_code ) ) {
			$this->failed_transaction_rate_limiter->bump();
		}
	}

	/**
	 * Returns true if the code returned from the API represents an error that should be rate-limited.
	 *
	 * @param string $error_code The error code returned from the API.
	 *
	 * @return bool Whether the rate limiter should be bumped.
	 */
	protected function should_bump_rate_limiter( string $error_code ): bool {
		return in_array( $error_code, [ 'card_declined', 'incorrect_number', 'incorrect_cvc' ], true );
	}
}
