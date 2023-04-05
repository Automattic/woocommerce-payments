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

	public function test_show_woopay_thankyou_notice_empty_order() {
		$result = $this->payments_order_success_page->show_woopay_thankyou_notice( 'Thank you', null );

		$this->assertSame( 'Thank you', $result );
	}

	public function test_show_woopay_thankyou_notice_order_without_woopay_meta() {
		$order = WC_Helper_Order::create_order();
		$order->save();

		$result = $this->payments_order_success_page->show_woopay_thankyou_notice( 'Thank you', $order );

		$this->assertSame( 'Thank you', $result );
	}

	public function test_show_woopay_thankyou_notice_order_with_woopay_meta() {
		$order = WC_Helper_Order::create_order();
		$order->add_meta_data( 'is_woopay', true );
		$order->save();

		$result = $this->payments_order_success_page->show_woopay_thankyou_notice( 'Thank you', $order );

		$this->assertSame( '<div class="thankyou-notice-woopay">Thank you! We’ve received your order.</div>', $result );
	}
}
