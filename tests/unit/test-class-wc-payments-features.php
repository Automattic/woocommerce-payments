<?php
/**
 * Class WC_Payments_Features_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Features unit tests.
 */
class WC_Payments_Features_Test extends WP_UnitTestCase {

	const FLAG_OPTION_NAME_TO_FRONTEND_KEY_MAPPING = [
		'_wcpay_feature_upe'                     => 'upe',
		'_wcpay_feature_upe_settings_preview'    => 'upeSettingsPreview',
		'_wcpay_feature_customer_multi_currency' => 'multiCurrency',
	];

	public function tear_down() {
		// Remove pre_option filters.
		foreach ( array_keys( self::FLAG_OPTION_NAME_TO_FRONTEND_KEY_MAPPING ) as $flag ) {
			remove_all_filters( 'pre_option_' . $flag );
		}
		delete_option( WC_Payments_Account::ACCOUNT_OPTION );
	}

	/**
	 * @dataProvider enabled_flags_provider
	 */
	public function test_it_returns_expected_to_array_result( array $enabled_flags ) {
		$this->setup_enabled_flags( $enabled_flags );

		$expected = [];
		foreach ( $enabled_flags as $flag ) {
			$frontend_key              = self::FLAG_OPTION_NAME_TO_FRONTEND_KEY_MAPPING[ $flag ];
			$expected[ $frontend_key ] = true;
		}

		$this->assertEquals( $expected, WC_Payments_Features::to_array() );
	}

	public function enabled_flags_provider() {
		return [
			'no flags'  => [ [] ],
			'all flags' => [ array_keys( self::FLAG_OPTION_NAME_TO_FRONTEND_KEY_MAPPING ) ],
		];
	}

	public function test_customer_multi_currency_is_enabled_by_default() {
		add_filter(
			'pre_option__wcpay_feature_customer_multi_currency',
			function ( $pre_option, $option, $default ) {
				return $default;
			},
			10,
			3
		);

		$this->assertTrue( WC_Payments_Features::is_customer_multi_currency_enabled() );
	}

	public function test_customer_multi_currency_can_be_disabled() {
		add_filter(
			'pre_option__wcpay_feature_customer_multi_currency',
			function ( $pre_option, $option, $default ) {
				return '0';
			},
			10,
			3
		);

		$this->assertFalse( WC_Payments_Features::is_customer_multi_currency_enabled() );
	}

	public function test_is_platform_checkout_eligible_returns_true() {
		add_option( WC_Payments_Account::ACCOUNT_OPTION, [ 'account' => [ 'platform_checkout_eligible' => true ] ] );
		$this->assertTrue( WC_Payments_Features::is_platform_checkout_eligible() );
	}

	public function test_is_platform_checkout_eligible_returns_false() {
		add_option( WC_Payments_Account::ACCOUNT_OPTION, [ 'account' => [ 'platform_checkout_eligible' => false ] ] );
		$this->assertFalse( WC_Payments_Features::is_platform_checkout_eligible() );
	}

	public function test_is_platform_checkout_eligible_returns_false_if_account_missing() {
		add_option( WC_Payments_Account::ACCOUNT_OPTION, [ 'account' => [] ] );
		$this->assertFalse( WC_Payments_Features::is_platform_checkout_eligible() );
	}

	private function setup_enabled_flags( array $enabled_flags ) {
		foreach ( array_keys( self::FLAG_OPTION_NAME_TO_FRONTEND_KEY_MAPPING ) as $flag ) {
			add_filter(
				'pre_option_' . $flag,
				function () use ( $flag, $enabled_flags ) {
					return in_array( $flag, $enabled_flags, true ) ? '1' : '0';
				}
			);
		}
	}
}
