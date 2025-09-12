<?php
/**
 * Tests for the dynamic checkout place order button feature flag.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Test class for dynamic checkout place order button feature flag.
 */
class Test_Dynamic_Checkout_Place_Order_Button extends WP_UnitTestCase {

	/**
	 * Test that the feature flag is properly defined.
	 */
	public function test_feature_flag_is_defined() {
		$this->assertTrue( method_exists( WC_Payments_Features::class, 'is_dynamic_checkout_place_order_button_enabled' ) );
	}

	/**
	 * Test that the feature flag returns a boolean value.
	 */
	public function test_feature_flag_returns_boolean() {
		$result = WC_Payments_Features::is_dynamic_checkout_place_order_button_enabled();
		$this->assertIsBool( $result );
	}

	/**
	 * Test that the feature flag is included in the frontend feature flags when enabled.
	 */
	public function test_feature_flag_included_in_frontend_flags_when_enabled() {
		// Enable the feature for this test.
		update_option( '_wcpay_feature_dynamic_checkout_place_order_button', '1' );

		$frontend_flags = WC_Payments_Features::to_array();

		$this->assertArrayHasKey( 'isDynamicCheckoutPlaceOrderButtonEnabled', $frontend_flags );
		$this->assertIsBool( $frontend_flags['isDynamicCheckoutPlaceOrderButtonEnabled'] );
		$this->assertTrue( $frontend_flags['isDynamicCheckoutPlaceOrderButtonEnabled'] );

		// Clean up.
		delete_option( '_wcpay_feature_dynamic_checkout_place_order_button' );
	}

	/**
	 * Test that the feature flag can be enabled via option.
	 */
	public function test_feature_flag_can_be_enabled_via_option() {
		// Enable the feature via option.
		update_option( '_wcpay_feature_dynamic_checkout_place_order_button', '1' );
		$this->assertTrue( WC_Payments_Features::is_dynamic_checkout_place_order_button_enabled() );

		// Disable the feature via option.
		update_option( '_wcpay_feature_dynamic_checkout_place_order_button', '0' );
		$this->assertFalse( WC_Payments_Features::is_dynamic_checkout_place_order_button_enabled() );

		// Clean up.
		delete_option( '_wcpay_feature_dynamic_checkout_place_order_button' );
	}

	/**
	 * Test that the feature flag defaults to disabled.
	 */
	public function test_feature_flag_defaults_to_disabled() {
		// Ensure the option is not set (default state).
		delete_option( '_wcpay_feature_dynamic_checkout_place_order_button' );
		$this->assertFalse( WC_Payments_Features::is_dynamic_checkout_place_order_button_enabled() );
	}

	/**
	 * Test that the feature flag is consistent between PHP and frontend.
	 */
	public function test_feature_flag_consistency_between_php_and_frontend() {
		// Enable the feature for this test.
		update_option( '_wcpay_feature_dynamic_checkout_place_order_button', '1' );

		$php_result      = WC_Payments_Features::is_dynamic_checkout_place_order_button_enabled();
		$frontend_flags  = WC_Payments_Features::to_array();
		$frontend_result = $frontend_flags['isDynamicCheckoutPlaceOrderButtonEnabled'];

		$this->assertEquals( $php_result, $frontend_result );
		$this->assertTrue( $php_result );
		$this->assertTrue( $frontend_result );

		// Clean up.
		delete_option( '_wcpay_feature_dynamic_checkout_place_order_button' );
	}
}
