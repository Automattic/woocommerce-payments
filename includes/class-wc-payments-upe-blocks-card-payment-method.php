<?php
/**
 * Class WC_Payments_UPE_Blocks_Card_Payment_Method
 *
 * @package WooCommerce\Payments
 */

/**
 * Credit Card
 */
class WC_Payments_UPE_Blocks_Card_Payment_Method extends WC_Payments_UPE_Blocks_Payment_Method {
	public function __construct()
	{
		$this->initialize();
	}

    /**
	 * Initializes the class.
	 */
	public function initialize() {
		$this->name    = WC_Payment_Gateway_WCPay::GATEWAY_ID;
		$this->gateway = WC_Payments::get_gateway();
	}
}
