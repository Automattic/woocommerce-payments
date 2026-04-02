<?php
/**
 * These tests make assertions against class WC_Payments_Express_Checkout_Total_Mismatch_Handler.
 *
 * @package WooCommerce\Payments\Tests
 */

use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use Automattic\WooCommerce\StoreApi\Payments\PaymentResult;

/**
 * WC_Payments_Express_Checkout_Total_Mismatch_Handler_Test class.
 */
class WC_Payments_Express_Checkout_Total_Mismatch_Handler_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WC_Payments_Express_Checkout_Total_Mismatch_Handler
	 */
	private $handler;

	/**
	 * @var WC_Product_Simple
	 */
	private $product;

	/**
	 * @var WC_Order
	 */
	private $order;

	public function set_up() {
		parent::set_up();

		$this->handler = new WC_Payments_Express_Checkout_Total_Mismatch_Handler();
		$this->handler->init();

		$this->product = WC_Helper_Product::create_simple_product();
		$this->product->set_price( 50 );
		$this->product->set_regular_price( 50 );
		$this->product->save();

		WC()->session->init();
		WC()->cart->empty_cart();
	}

	public function tear_down() {
		WC()->cart->empty_cart();
		WC()->session->cleanup_sessions();

		if ( $this->order instanceof WC_Order ) {
			$this->order->delete( true );
		}

		if ( $this->product instanceof WC_Product ) {
			$this->product->delete( true );
		}

		remove_filter( 'wc_tax_enabled', '__return_true' );
		update_option( 'woocommerce_tax_based_on', 'shipping' );

		parent::tear_down();
	}

	public function test_no_mismatch_when_totals_match() {
		WC()->cart->add_to_cart( $this->product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$this->handler->capture_session_cart_total( WC()->cart );

		$cart_total  = (float) WC()->cart->get_total( 'edit' );
		$this->order = WC_Helper_Order::create_order( 0, $cart_total );

		$context = new PaymentContext();
		$context->set_payment_method( 'woocommerce_payments' );
		$context->set_order( $this->order );
		$context->set_payment_data( [ 'express_payment_type' => 'google_pay' ] );

		$result = new PaymentResult();
		$this->handler->check_ece_total_mismatch( $context, $result );

		$this->assertNotEquals( 'failure', $result->status );
	}

	public function test_mismatch_detected_when_totals_differ() {
		WC()->cart->add_to_cart( $this->product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$this->handler->capture_session_cart_total( WC()->cart );

		// Simulate tax recalculation adding $5 to the total.
		$original_total = (float) WC()->cart->get_total( 'edit' );
		$this->order    = WC_Helper_Order::create_order( 0, $original_total + 5.00 );

		$context = new PaymentContext();
		$context->set_payment_method( 'woocommerce_payments' );
		$context->set_order( $this->order );
		$context->set_payment_data( [ 'express_payment_type' => 'apple_pay' ] );

		$result = new PaymentResult();
		$this->handler->check_ece_total_mismatch( $context, $result );

		$this->assertEquals( 'failure', $result->status );
		$this->assertStringContainsString( 'order-pay', $result->redirect_url );
		$this->assertStringContainsString( 'wcpay_ece_mismatch=1', $result->redirect_url );
		$this->assertArrayHasKey( 'errorMessage', $result->payment_details );
		$this->assertStringContainsString( 'order total has been updated', $result->payment_details['errorMessage'] );
	}

	public function test_mismatch_check_skipped_for_non_ece_payment() {
		WC()->cart->add_to_cart( $this->product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$this->handler->capture_session_cart_total( WC()->cart );

		$this->order = WC_Helper_Order::create_order( 0, 100 );

		$context = new PaymentContext();
		$context->set_payment_method( 'woocommerce_payments' );
		$context->set_order( $this->order );
		$context->set_payment_data( [] );

		$result = new PaymentResult();
		$this->handler->check_ece_total_mismatch( $context, $result );

		$this->assertNotEquals( 'failure', $result->status );
	}

	public function test_mismatch_check_skipped_for_other_gateways() {
		WC()->cart->add_to_cart( $this->product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$this->handler->capture_session_cart_total( WC()->cart );

		$this->order = WC_Helper_Order::create_order( 0, 100 );

		$context = new PaymentContext();
		$context->set_payment_method( 'stripe' );
		$context->set_order( $this->order );
		$context->set_payment_data( [ 'express_payment_type' => 'google_pay' ] );

		$result = new PaymentResult();
		$this->handler->check_ece_total_mismatch( $context, $result );

		$this->assertNotEquals( 'failure', $result->status );
	}

	public function test_session_total_not_captured_when_cart_empty() {
		// Empty cart simulates pay-for-order page where cart isn't used.
		$this->assertTrue( WC()->cart->is_empty() );
		$this->handler->capture_session_cart_total( WC()->cart );

		$this->order = WC_Helper_Order::create_order( 0, 50 );

		$context = new PaymentContext();
		$context->set_payment_method( 'woocommerce_payments' );
		$context->set_order( $this->order );
		$context->set_payment_data( [ 'express_payment_type' => 'google_pay' ] );

		$result = new PaymentResult();
		$this->handler->check_ece_total_mismatch( $context, $result );

		$this->assertNotEquals( 'failure', $result->status );
	}

	public function test_mismatch_data_recorded_in_order_meta() {
		WC()->cart->add_to_cart( $this->product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$this->handler->capture_session_cart_total( WC()->cart );

		$original_total = (float) WC()->cart->get_total( 'edit' );
		$this->order    = WC_Helper_Order::create_order( 0, $original_total + 10.00 );

		$context = new PaymentContext();
		$context->set_payment_method( 'woocommerce_payments' );
		$context->set_order( $this->order );
		$context->set_payment_data( [ 'express_payment_type' => 'apple_pay' ] );

		$result = new PaymentResult();
		$this->handler->check_ece_total_mismatch( $context, $result );

		$this->order   = wc_get_order( $this->order->get_id() );
		$mismatch_data = $this->order->get_meta( '_wcpay_ece_total_mismatch' );

		$this->assertNotEmpty( $mismatch_data );
		$this->assertArrayHasKey( 'expected', $mismatch_data );
		$this->assertArrayHasKey( 'actual', $mismatch_data );
		$this->assertArrayHasKey( 'difference', $mismatch_data );
		$this->assertArrayHasKey( 'currency', $mismatch_data );
		$this->assertArrayHasKey( 'timestamp', $mismatch_data );
		$this->assertEquals( 10.0, $mismatch_data['difference'] );
	}

	public function test_small_difference_within_threshold_tolerated() {
		WC()->cart->add_to_cart( $this->product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$this->handler->capture_session_cart_total( WC()->cart );

		$original_total = (float) WC()->cart->get_total( 'edit' );
		$this->order    = WC_Helper_Order::create_order( 0, $original_total + 0.01 );

		$context = new PaymentContext();
		$context->set_payment_method( 'woocommerce_payments' );
		$context->set_order( $this->order );
		$context->set_payment_data( [ 'express_payment_type' => 'google_pay' ] );

		$result = new PaymentResult();
		$this->handler->check_ece_total_mismatch( $context, $result );

		$this->assertNotEquals( 'failure', $result->status );
	}

	public function test_difference_above_threshold_triggers_mismatch() {
		WC()->cart->add_to_cart( $this->product->get_id(), 1 );
		WC()->cart->calculate_totals();
		$this->handler->capture_session_cart_total( WC()->cart );

		$original_total = (float) WC()->cart->get_total( 'edit' );
		$this->order    = WC_Helper_Order::create_order( 0, $original_total + 0.02 );

		$context = new PaymentContext();
		$context->set_payment_method( 'woocommerce_payments' );
		$context->set_order( $this->order );
		$context->set_payment_data( [ 'express_payment_type' => 'google_pay' ] );

		$result = new PaymentResult();
		$this->handler->check_ece_total_mismatch( $context, $result );

		$this->assertEquals( 'failure', $result->status );
	}
}
