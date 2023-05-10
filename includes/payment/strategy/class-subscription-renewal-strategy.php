<?php
/**
 * Class Subscription_Renewal_Strategy
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Strategy;

use WCPay\Payment\Payment;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;

/**
 * Performs a standard scheduled subscription renewal payment.
 */
class Subscription_Renewal_Strategy extends Standard_Payment_Strategy {
	/**
	 * Prepares the request for creating an intention.
	 *
	 * @param Payment $payment The payment, which should be directed to the server.
	 * @return Create_And_Confirm_Intention
	 */
	protected function prepare_intent_request( Payment $payment ) {
		$request = parent::prepare_intent_request( $payment );

		$mandate = $this->gateway->get_mandate_param_for_renewal_order( $payment->get_order() );
		if ( $mandate ) {
			$request->set_mandate( $mandate );
		}

		return $request;
	}
}
