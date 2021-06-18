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
		'_wcpay_feature_upe'                  => 'upe',
		'_wcpay_feature_upe_settings_preview' => 'upeSettingsPreview',
	];

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
