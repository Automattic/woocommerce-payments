<?php
/**
 * Class WC_Payments_Features_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Features unit tests.
 */
class WC_Payments_Features_Test extends WCPAY_UnitTestCase {

	const FLAG_OPTION_NAME_TO_FRONTEND_KEY_MAPPING = [
		'_wcpay_feature_customer_multi_currency'    => 'multiCurrency',
		'_wcpay_feature_documents'                  => 'documents',
		'_wcpay_feature_account_overview_task_list' => 'accountOverviewTaskList',
		'_wcpay_feature_custom_deposit_schedules'   => 'customDepositSchedules',
	];

	public function set_up() {
		// Mock the main class's cache service.
		$this->_cache     = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );
	}

	public function tear_down() {
		// Remove pre_option filters.
		foreach ( array_keys( self::FLAG_OPTION_NAME_TO_FRONTEND_KEY_MAPPING ) as $flag ) {
			remove_all_filters( 'pre_option_' . $flag );
		}

		// Restore the cache service in the main class.
		WC_Payments::set_database_cache( $this->_cache );

		parent::tear_down();
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
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->assertTrue( WC_Payments_Features::is_platform_checkout_eligible() );
	}

	public function test_is_platform_checkout_eligible_returns_false() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => false ] );
		$this->assertFalse( WC_Payments_Features::is_platform_checkout_eligible() );
	}

	public function test_is_documents_section_enabled_returns_true_when_flag_is_true() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'is_documents_enabled' => true ] );
		$this->assertTrue( WC_Payments_Features::is_documents_section_enabled() );
		$this->assertTrue( WC_Payments_Features::to_array()['documents'] );
	}

	public function test_is_documents_section_enabled_returns_false_when_flag_is_false() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'is_documents_enabled' => false ] );
		$this->assertFalse( WC_Payments_Features::is_documents_section_enabled() );
		$this->assertArrayNotHasKey( 'documents', WC_Payments_Features::to_array() );
	}

	public function test_is_documents_section_enabled_returns_false_when_flag_is_not_set() {
		$this->mock_cache->method( 'get' )->willReturn( [] );
		$this->assertFalse( WC_Payments_Features::is_documents_section_enabled() );
		$this->assertArrayNotHasKey( 'documents', WC_Payments_Features::to_array() );
	}

	public function test_is_documents_section_enabled_returns_false_when_cache_is_not_set() {
		$this->mock_cache->method( 'get' )->willReturn( null );
		$this->assertFalse( WC_Payments_Features::is_documents_section_enabled() );
		$this->assertArrayNotHasKey( 'documents', WC_Payments_Features::to_array() );
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
