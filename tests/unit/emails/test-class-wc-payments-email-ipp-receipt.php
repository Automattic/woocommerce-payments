<?php
/**
 * Class WC_Payments_Email_IPP_Receipt_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Email_IPP_Receipt unit tests.
 */
class WC_Payments_Email_IPP_Receipt_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Email_IPP_Receipt
	 */
	private $email;

	/**
	 * Setup test.
	 */
	public function set_up() {
		parent::set_up();

		// Load the email class - it extends WC_Email which requires WooCommerce to be initialized.
		if ( ! class_exists( 'WC_Payments_Email_IPP_Receipt' ) ) {
			require_once WCPAY_ABSPATH . 'includes/emails/class-wc-payments-email-ipp-receipt.php';
		}

		$this->email = new WC_Payments_Email_IPP_Receipt();
	}

	/**
	 * Test that get_preview_order returns false when passed false.
	 *
	 * This ensures the method handles third-party plugin email previews gracefully,
	 * where WooCommerce may pass false instead of a WC_Order object.
	 *
	 * @see https://linear.app/a8c/issue/WOOPMNT-5617
	 */
	public function test_get_preview_order_returns_false_when_passed_false() {
		$result = $this->email->get_preview_order( false );
		$this->assertFalse( $result );
	}

	/**
	 * Test that get_preview_order returns null when passed null.
	 */
	public function test_get_preview_order_returns_null_when_passed_null() {
		$result = $this->email->get_preview_order( null );
		$this->assertNull( $result );
	}

	/**
	 * Test that get_preview_order sets payment method title when passed a valid order.
	 */
	public function test_get_preview_order_sets_payment_method_title_for_valid_order() {
		$order = WC_Helper_Order::create_order();

		$result = $this->email->get_preview_order( $order );

		$this->assertInstanceOf( WC_Order::class, $result );
		$this->assertSame( $order, $result );
		$this->assertSame( 'WooCommerce In-Person Payments', $result->get_payment_method_title() );
	}
}
