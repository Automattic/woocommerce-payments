<?php
/**
 * Class WC_Payments_Express_Checkout_Button_Handler_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Express_Checkout_Button_Handler unit tests.
 */
class WC_Payments_Express_Checkout_Button_Handler_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Handler
	 */
	private $system_under_test;

	/**
	 * Mock WC_Payments_Account.
	 *
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_wcpay_account;

	/**
	 * Mock WC_Payment_Gateway_WCPay.
	 *
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_wcpay_gateway;

	/**
	 * Mock Express Checkout Button Helper.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper|MockObject
	 */
	private $mock_ece_button_helper;

	/**
	 * Mock Express Checkout Ajax Handler.
	 *
	 * @var WC_Payments_Express_Checkout_Ajax_Handler|MockObject
	 */
	private $mock_express_checkout_ajax_handler;



	/**
	 * Shipping zone.
	 *
	 * @var WC_Shipping_Zone
	 */
	private $zone;

	/**
	 * Flat rate shipping method ID.
	 *
	 * @var int
	 */
	private $flat_rate_id;

	/**
	 * Local pickup shipping method ID.
	 *
	 * @var int
	 */
	private $local_pickup_id;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		WC()->shipping()->unregister_shipping_methods();

		$this->mock_wcpay_account                 = $this->createMock( WC_Payments_Account::class );
		$this->mock_wcpay_gateway                 = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->mock_ece_button_helper             = $this->createMock( WC_Payments_Express_Checkout_Button_Helper::class );
		$this->mock_express_checkout_ajax_handler = $this->createMock( WC_Payments_Express_Checkout_Ajax_Handler::class );

		$this->system_under_test = new WC_Payments_Express_Checkout_Button_Handler(
			$this->mock_wcpay_account,
			$this->mock_wcpay_gateway,
			$this->mock_ece_button_helper,
			$this->mock_express_checkout_ajax_handler
		);

		// Set up shipping zones and methods.
		$this->zone = new WC_Shipping_Zone();
		$this->zone->set_zone_name( 'Worldwide' );
		$this->zone->set_zone_order( 1 );
		$this->zone->save();

		$flat_rate          = $this->zone->add_shipping_method( 'flat_rate' );
		$this->flat_rate_id = $flat_rate;

		$local_pickup          = $this->zone->add_shipping_method( 'local_pickup' );
		$this->local_pickup_id = $local_pickup;
	}

	public function tear_down() {
		parent::tear_down();

		// Clean up shipping zones and methods.
		$this->zone->delete();
	}

	public function test_filter_cart_needs_shipping_address_regular_products() {
		$this->assertEquals(
			true,
			$this->system_under_test->filter_cart_needs_shipping_address( true ),
			'Should not modify shipping address requirement for regular products'
		);
	}


	public function test_filter_cart_needs_shipping_address_subscription_products() {
		WC_Subscriptions_Cart::set_cart_contains_subscription( true );
		$this->mock_ece_button_helper->method( 'is_checkout' )->willReturn( true );

		$this->zone->delete_shipping_method( $this->flat_rate_id );
		$this->zone->delete_shipping_method( $this->local_pickup_id );

		$this->assertFalse(
			$this->system_under_test->filter_cart_needs_shipping_address( true ),
			'Should not require shipping address for subscription without shipping methods'
		);

		remove_filter( 'woocommerce_shipping_method_count', '__return_zero' );
		WC_Subscriptions_Cart::set_cart_contains_subscription( false );
	}
}
