<?php
/**
 * These tests make assertions against class WC_Payments_Express_Checkout_Button_Helper.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Payment_Request_Button_Handler_Test class.
 */
class WC_Payments_Express_Checkout_Button_Helper_Test extends WCPAY_UnitTestCase {

	/**
	 * WC_Payments_Account instance.
	 *
	 * @var WC_Payments_Account
	 */
	private $mock_wcpay_account;

	/**
	 * Test shipping zone.
	 *
	 * @var WC_Shipping_Zone
	 */
	private $zone;

	/**
	 * Express Checkout Helper instance.
	 *
	 * @var WC_Payments_Express_Checkout_Button_Helper
	 */
	private $express_checkout_helper;

	/**
	 * Instance ID of default shipping zone shipping method.
	 *
	 * @var int
	 */
	private $default_zone_shipping_method_id;

	/**
	 * Sets up things all tests need.
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );

		$this->express_checkout_helper = new WC_Payments_Express_Checkout_Button_Helper( $this->mock_wcpay_account );

		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'Worldwide' );
		$zone->set_zone_order( 1 );
		$zone->save();

		$this->flat_rate_id = $zone->add_shipping_method( 'flat_rate' );

		$this->zone = $zone;
	}

	public function tear_down() {
		parent::tear_down();
		$this->zone->delete();

		// Delete the shipping method from the default shipping zone.
		$default_zone = \WC_Shipping_Zones::get_zone( 0 );
		$default_zone->delete_shipping_method( $this->default_zone_shipping_method_id );
	}

	public function test_has_any_shipping_method_returns_true_when_default_zone_has_shipping_method() {
		$default_zone                          = \WC_Shipping_Zones::get_zone( 0 );
		$this->default_zone_shipping_method_id = $default_zone->add_shipping_method( 'free_shipping' );

		$this->assertTrue( $this->express_checkout_helper->has_any_shipping_method() );
	}

	public function test_has_any_shipping_method_returns_true_when_non_default_zone_has_shipping_method() {
		// A shipping zone has already been created in the setup method and has a flat rate shipping method.
		$this->assertTrue( $this->express_checkout_helper->has_any_shipping_method() );
	}

	public function test_has_any_shipping_method_returns_false_when_no_shipping_methods_are_present() {
		// Delete the shipping method added in the setup method.
		$this->zone->delete();
		$this->assertFalse( $this->express_checkout_helper->has_any_shipping_method() );
	}

}
