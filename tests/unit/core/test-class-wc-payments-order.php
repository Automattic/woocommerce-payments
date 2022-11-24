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

	public function test_set_payment_intent_id() {
		$wcpay_order = new WC_Payments_Order( WC_Helper_Order::create_order() );
		$intent_id   = 'pi_mock_123';
		$wcpay_order->set_payment_intent_id( $intent_id );
		$this->assertEquals( $wcpay_order->get_wc_order()->get_meta( '_intent_id', true ), $intent_id );
		$this->assertSame( 1, did_action( 'wcpay_payment_intent_id_updated' ) );
		$this->assertSame( 0, did_action( 'wcpay_payment_method_id_updated' ) );
	}

	public function test_get_payment_intent_id() {
		$intent_id   = 'pi_mock';
		$wcpay_order = new WC_Payments_Order( WC_Helper_Order::create_order() );
		$wcpay_order->get_wc_order()->update_meta_data( '_intent_id', $intent_id );
		$this->assertEquals( $wcpay_order->get_payment_intent_id(), $intent_id );
	}

	public function test_set_payment_method_id() {
		$wcpay_order    = new WC_Payments_Order( WC_Helper_Order::create_order() );
		$payment_method = 'pm_mock';
		$wcpay_order->set_payment_method_id( $payment_method );
		$this->assertEquals( $wcpay_order->get_wc_order()->get_meta( '_payment_method_id', true ), $payment_method );
		$this->assertSame( 0, did_action( 'wcpay_payment_intent_id_updated' ) );
		$this->assertSame( 1, did_action( 'wcpay_payment_method_id_updated' ) );
	}

	public function test_get_payment_method_id() {
		$payment_method_id = 'pm_mock_123';
		$wcpay_order       = new WC_Payments_Order( WC_Helper_Order::create_order() );
		$wcpay_order->get_wc_order()->update_meta_data( '_payment_method_id', $payment_method_id );
		$this->assertEquals( $wcpay_order->get_payment_method_id(), $payment_method_id );
	}

	public function test_attach_intent_info_to_order() {
		$order          = WC_Helper_Order::create_order();
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
