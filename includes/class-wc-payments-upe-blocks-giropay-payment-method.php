<?php
/**
 * Class WC_Payments_UPE_Blocks_Giropay_Payment_Method
 *
 * @package WooCommerce\Payments
 */

use WCPay\Payment_Methods\Giropay_Payment_Method;

/**
 * Giropay
 */
class WC_Payments_UPE_Blocks_Giropay_Payment_Method extends WC_Payments_UPE_Blocks_Payment_Method {
    public function __construct()
	{
		$this->initialize();
	}

    /**
	 * Initializes the class.
	 */
	public function initialize() {
		$this->name    = Giropay_Payment_Method::PAYMENT_METHOD_STRIPE_ID;
		$this->gateway = WC_Payments::get_payment_gateway_by_id($this->name);
	}
}
