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
		'_wcpay_feature_grouped_settings' => 'groupedSettings',
		'_wcpay_feature_giropay'          => 'giropay',
		'_wcpay_feature_sepa'             => 'sepa',
		'_wcpay_feature_sofort'           => 'sofort',
		'_wcpay_feature_upe'              => 'upe',
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
			'no flags'              => [ [] ],
			'only grouped settings' => [ [ '_wcpay_feature_grouped_settings' ] ],
			'all flags'             => [ array_keys( self::FLAG_OPTION_NAME_TO_FRONTEND_KEY_MAPPING ) ],
		];
	}

	private function setup_enabled_flags( array $enabled_flags ) {
		foreach ( $enabled_flags as $flag ) {
			add_filter(
				'pre_option_' . $flag,
				function () {
					return '1';
				}
			);
		}
	}
}
