<?php
/**
 * Class Final_state
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment\State;

/**
 * Represents a payment in the complete state, which requires no further processing.
 */
final class Final_state extends Payment_State {
	public function a() {

		// ( new Step\Check_Session_Against_Processing_Order_Step() )->complete();
		// ( new Step\Update_Saved_Payment_Method_Step() )->complete(); // When using a saved PM.
		// ( new Step\Save_Payment_Method_Step() )->complete(); // Saves PM.
		// ( new Step\Store_Metadata_Step() )->complete();
		// ( new Step\Update_Order_Step() )->complete();
		// ( new Step\Add_Token_To_Order_Step() )->complete(); // Saved PM.
		// ( new Step\Cleanup_Step() )->complete();
	}
}
