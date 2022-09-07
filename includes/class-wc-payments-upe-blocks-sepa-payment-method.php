<?php
/**
 * Class WC_Payments_UPE_Blocks_Sepa_Payment_Method
 *
 * @package WooCommerce\Payments
 */

use WCPay\Payment_Methods\Sepa_Payment_Method;

/**
 * Sepa debit
 */
class WC_Payments_UPE_Blocks_Sepa_Payment_Method extends WC_Payments_UPE_Blocks_Payment_Method {
	public function __construct()
	{
		$this->initialize();
	}

    /**
	 * Initializes the class.
	 */
	public function initialize() {
		$this->name    = Sepa_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$this->gateway = WC_Payments::get_payment_gateway_by_id($this->name);
	}
}
