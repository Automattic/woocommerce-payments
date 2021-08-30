<?php
/**
 * Class WC_Payments_Subscriptions_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Subscriptions unit tests.
 */
class WC_Payments_Subscriptions_Test extends WP_UnitTestCase {

	/**
	 * Tests WC_Payments_Subscriptions::get_product_service().
	 */
	public function test_get_product_service() {
		$this->assertInstanceOf( 'WC_Payments_Product_Service', WC_Payments_Subscriptions::get_product_service() );
	}

	/**
	 * Tests WC_Payments_Subscriptions::get_invoice_service().
	 */
	public function test_get_invoice_service() {
		$this->assertInstanceOf( 'WC_Payments_Invoice_Service', WC_Payments_Subscriptions::get_invoice_service() );
	}

	/**
	 * Tests WC_Payments_Subscriptions::get_subscription_service().
	 */
	public function test_get_subscription_service() {
		$this->assertInstanceOf( 'WC_Payments_Subscription_Service', WC_Payments_Subscriptions::get_subscription_service() );
	}

	/**
	 * Tests WC_Payments_Subscriptions::get_subscription_service().
	 */
	public function test_get_event_handler() {
		$this->assertInstanceOf( 'WC_Payments_Subscriptions_Event_Handler', WC_Payments_Subscriptions::get_event_handler() );
	}
}
