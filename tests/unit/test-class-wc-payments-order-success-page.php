<?php
/**
 * Class WC_Payments_Order_Success_Page_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Order_Success_Page unit tests.
 */
class WC_Payments_Order_Success_Page_Test extends WCPAY_UnitTestCase {
	/**
	 * WC_Payments_Order_Success_Page Instance.
	 *
	 * @var WC_Payments_Order_Success_Page
	 */
	private $payments_order_success_page;

	public function set_up() {
		parent::set_up();

		$this->payments_order_success_page = new WC_Payments_Order_Success_Page();
	}

	public function test_show_woopay_payment_method_name_empty_order() {
		$method_name = 'Credit card';
		$result      = $this->payments_order_success_page->show_woopay_payment_method_name( $method_name, null );

		$this->assertSame( $method_name, $result );
	}

	public function test_show_woopay_payment_method_name_without_woopay_meta() {
		$order = WC_Helper_Order::create_order();
		$order->save();

		$method_name = 'Credit card';
		$result      = $this->payments_order_success_page->show_woopay_payment_method_name( $method_name, $order );

		$this->assertSame( $method_name, $result );
	}

	public function test_show_woopay_payment_method_name_order_with_woopay_meta() {
		$order = WC_Helper_Order::create_order();
		$order->add_meta_data( 'is_woopay', true );
		$order->add_meta_data( 'last4', '1234' );
		$order->save();

		add_filter( 'woocommerce_is_order_received_page', '__return_true' );
		$result = $this->payments_order_success_page->show_woopay_payment_method_name( 'Credit card', $order );
		remove_filter( 'woocommerce_is_order_received_page', '__return_true' );

		$this->assertStringContainsString( 'wc-payment-gateway-method-name-woopay-wrapper', $result );
		$this->assertStringContainsString( 'img alt="WooPay"', $result );
		$this->assertStringContainsString( sprintf( 'Card ending in %s', $order->get_meta( 'last4' ) ), $result );
	}
}
