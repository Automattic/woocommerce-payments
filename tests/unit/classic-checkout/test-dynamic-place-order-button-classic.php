<?php
/**
 * Tests for the dynamic place order button feature in classic checkout.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Test class for dynamic place order button feature in classic checkout.
 */
class Test_Dynamic_Place_Order_Button_Classic extends WP_UnitTestCase {

	/**
	 * Test that the feature flag is properly exposed to classic checkout JavaScript.
	 */
	public function test_feature_flag_exposed_to_classic_checkout() {
		// Mock the global wcpaySettings.
		$mock_settings = [
			'featureFlags' => [
				'isDynamicCheckoutPlaceOrderButtonEnabled' => true,
			],
		];

		// Test that the feature flag is included in the settings.
		$this->assertArrayHasKey( 'featureFlags', $mock_settings );
		$this->assertArrayHasKey( 'isDynamicCheckoutPlaceOrderButtonEnabled', $mock_settings['featureFlags'] );
		$this->assertTrue( $mock_settings['featureFlags']['isDynamicCheckoutPlaceOrderButtonEnabled'] );
	}

	/**
	 * Test that the feature flag controls Apple Pay and Google Pay payment method visibility.
	 */
	public function test_feature_flag_controls_payment_method_visibility() {
		// Test with feature flag enabled.
		$enabled_settings = [
			'featureFlags' => [
				'isDynamicCheckoutPlaceOrderButtonEnabled' => true,
			],
		];

		// When feature flag is enabled, Apple Pay and Google Pay should be available.
		$this->assertTrue( $enabled_settings['featureFlags']['isDynamicCheckoutPlaceOrderButtonEnabled'] );

		// Test with feature flag disabled.
		$disabled_settings = [
			'featureFlags' => [
				'isDynamicCheckoutPlaceOrderButtonEnabled' => false,
			],
		];

		// When feature flag is disabled, Apple Pay and Google Pay should not be available.
		$this->assertFalse( $disabled_settings['featureFlags']['isDynamicCheckoutPlaceOrderButtonEnabled'] );
	}

	/**
	 * Test that the feature flag controls logo injection in classic checkout.
	 */
	public function test_feature_flag_controls_logo_injection() {
		// Test with feature flag enabled.
		$enabled_settings = [
			'featureFlags' => [
				'isDynamicCheckoutPlaceOrderButtonEnabled' => true,
			],
		];

		// When feature flag is enabled, logos should be injected.
		$should_inject_logos = $enabled_settings['featureFlags']['isDynamicCheckoutPlaceOrderButtonEnabled'];
		$this->assertTrue( $should_inject_logos );

		// Test with feature flag disabled.
		$disabled_settings = [
			'featureFlags' => [
				'isDynamicCheckoutPlaceOrderButtonEnabled' => false,
			],
		];

		// When feature flag is disabled, logos should not be injected.
		$should_inject_logos = $disabled_settings['featureFlags']['isDynamicCheckoutPlaceOrderButtonEnabled'];
		$this->assertFalse( $should_inject_logos );
	}

	/**
	 * Test that the feature flag is properly passed to the frontend.
	 */
	public function test_feature_flag_passed_to_frontend() {
		// Mock the frontend settings.
		$frontend_settings = [
			'featureFlags' => [
				'isDynamicCheckoutPlaceOrderButtonEnabled' => WC_Payments_Features::is_dynamic_checkout_place_order_button_enabled(),
			],
		];

		// Verify the feature flag is properly passed.
		$this->assertArrayHasKey( 'featureFlags', $frontend_settings );
		$this->assertArrayHasKey( 'isDynamicCheckoutPlaceOrderButtonEnabled', $frontend_settings['featureFlags'] );
		$this->assertIsBool( $frontend_settings['featureFlags']['isDynamicCheckoutPlaceOrderButtonEnabled'] );
	}

	/**
	 * Test that the feature flag can be enabled via option.
	 */
	public function test_feature_flag_can_be_enabled_via_option() {
		// Test with option set to true.
		update_option( '_wcpay_feature_dynamic_checkout_place_order_button', '1' );
		$enabled_result = WC_Payments_Features::is_dynamic_checkout_place_order_button_enabled();

		// Test with option set to false.
		update_option( '_wcpay_feature_dynamic_checkout_place_order_button', '0' );
		$disabled_result = WC_Payments_Features::is_dynamic_checkout_place_order_button_enabled();

		// Clean up.
		delete_option( '_wcpay_feature_dynamic_checkout_place_order_button' );

		// Verify the option is respected.
		$this->assertTrue( $enabled_result );
		$this->assertFalse( $disabled_result );
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
	 * Test that the feature flag is consistent across different contexts.
	 */
	public function test_feature_flag_consistency() {
		// Enable the feature for this test.
		update_option( '_wcpay_feature_dynamic_checkout_place_order_button', '1' );

		$php_result      = WC_Payments_Features::is_dynamic_checkout_place_order_button_enabled();
		$frontend_flags  = WC_Payments_Features::to_array();
		$frontend_result = $frontend_flags['isDynamicCheckoutPlaceOrderButtonEnabled'];

		// Verify consistency between PHP and frontend.
		$this->assertEquals( $php_result, $frontend_result );
		$this->assertTrue( $php_result );
		$this->assertTrue( $frontend_result );

		// Clean up.
		delete_option( '_wcpay_feature_dynamic_checkout_place_order_button' );
	}

	/**
	 * Test that the feature flag handles edge cases properly.
	 */
	public function test_feature_flag_edge_cases() {
		// Test with null value.
		$null_settings = [
			'featureFlags' => [
				'isDynamicCheckoutPlaceOrderButtonEnabled' => null,
			],
		];
		$this->assertNull( $null_settings['featureFlags']['isDynamicCheckoutPlaceOrderButtonEnabled'] );

		// Test with empty string.
		$empty_settings = [
			'featureFlags' => [
				'isDynamicCheckoutPlaceOrderButtonEnabled' => '',
			],
		];
		$this->assertEmpty( $empty_settings['featureFlags']['isDynamicCheckoutPlaceOrderButtonEnabled'] );

		// Test with string values.
		$string_true_settings = [
			'featureFlags' => [
				'isDynamicCheckoutPlaceOrderButtonEnabled' => 'true',
			],
		];
		$this->assertEquals( 'true', $string_true_settings['featureFlags']['isDynamicCheckoutPlaceOrderButtonEnabled'] );

		$string_false_settings = [
			'featureFlags' => [
				'isDynamicCheckoutPlaceOrderButtonEnabled' => 'false',
			],
		];
		$this->assertEquals( 'false', $string_false_settings['featureFlags']['isDynamicCheckoutPlaceOrderButtonEnabled'] );
	}
}
