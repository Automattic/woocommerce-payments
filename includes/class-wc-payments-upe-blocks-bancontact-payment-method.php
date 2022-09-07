<?php
/**
 * Class WC_Payments_UPE_Blocks_Bancontact_Payment_Method
 *
 * @package WooCommerce\Payments
 */

use WCPay\Payment_Methods\Bancontact_Payment_Method;

/**
 * Bancontact
 */
class WC_Payments_UPE_Blocks_Bancontact_Payment_Method extends WC_Payments_UPE_Blocks_Payment_Method {
	public function __construct()
	{
		$this->initialize();
	}

    /**
	 * Initializes the class.
	 */
	public function initialize() {
		$this->name    = Bancontact_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$this->gateway = WC_Payments::get_payment_gateway_by_id($this->name);
	}
}
