<?php
/**
 * Class WC_Payments_Order_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\WC_Payments_Order;

/**
 * WC_Payments_Order unit tests.
 */
class WC_Payments_Order_Test extends WCPAY_UnitTestCase {

	public function test_attach_intent_info_to_order() {
		$order = WC_Helper_Order::create_order();
		$wcpay_order    = new WC_Payments_Order( $order );
		$intent_id      = 'pi_mock';
		$intent_status  = 'succeeded';
		$payment_method = 'woocommerce_payments';
		$customer_id    = 'cus_12345';
		$charge_id      = 'ch_mock';
		$currency       = 'USD';
		$wcpay_order->attach_intent_info_to_order( $intent_id, $intent_status, $payment_method, $customer_id, $charge_id, $currency );

		$this->assertEquals( $intent_id, $wcpay_order->get_payment_intent_id() );
	}

}
