<?php
/**
 * Class UPE_Update_Intent_Strategy
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\Strategy;

use WCPay\Payment\Payment;
use WCPay\Payment\State\Authentication_Required_State;
use WCPay\Payment\State\Processed_State;

/**
 * Represents a payment processing strategy.
 */
class UPE_Update_Intent_Strategy extends Strategy {
	use Redirect_If_Action_Is_Required;



	public function process( Payment $payment ) {
		// update_intent...

		if ( $intent_requires_3ds ) {
			return new Authentication_Required_State( $payment );
		} else {
			return new UPE_Requires_Confirmation_State( $payment );

		}
	}
}

class UPE_Requires_Confirmation_State {
	function check_intent() {
		$this->context->switch_state( new Processed_State( $this->context ) );
	}
}

Verified_State
  - UPE_Requires_Confirmation_State
Processed_State
