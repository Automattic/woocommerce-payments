<?php
/**
 * Class WC_Payments_Features_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Database_Cache;

/**
 * WC_Payments_Features unit tests.
 */
class WC_Payments_Features_Test extends WCPAY_UnitTestCase {

	/**
	 * @var Database_Cache|MockObject
	 */
	protected $mock_cache;

	const FLAG_OPTION_NAME_TO_FRONTEND_KEY_MAPPING = [
		'_wcpay_feature_upe'                        => 'upe',
		'_wcpay_feature_upe_split'                  => 'upeSplit',
		'_wcpay_feature_upe_deferred_intent'        => 'upeDeferred',
		'_wcpay_feature_upe_settings_preview'       => 'upeSettingsPreview',
		'_wcpay_feature_customer_multi_currency'    => 'multiCurrency',
		'_wcpay_feature_documents'                  => 'documents',
		'_wcpay_feature_account_overview_task_list' => 'accountOverviewTaskList',
		'_wcpay_feature_auth_and_capture'           => 'isAuthAndCaptureEnabled',
		'_wcpay_feature_progressive_onboarding'     => 'progressiveOnboarding',
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

	public function test_is_woopay_eligible_returns_true() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->assertTrue( WC_Payments_Features::is_woopay_eligible() );
	}

	public function test_is_woopay_eligible_returns_false() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => false ] );
		$this->assertFalse( WC_Payments_Features::is_woopay_eligible() );
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

	public function test_are_payments_enabled_returns_true_when_payments_enabled() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'payments_enabled' => true ] );
		$this->assertTrue( WC_Payments_Features::are_payments_enabled() );
	}

	public function test_are_payments_enabled_returns_false_when_payments_disabled() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'payments_enabled' => false ] );
		$this->assertFalse( WC_Payments_Features::are_payments_enabled() );
	}

	public function test_are_payments_enabled_returns_false_when_cache_is_not_set() {
		$this->mock_cache->method( 'get' )->willReturn( null );
		$this->assertFalse( WC_Payments_Features::are_payments_enabled() );
	}

	public function test_are_payments_enabled_returns_false_when_flag_not_set() {
		$this->mock_cache->method( 'get' )->willReturn( [] );
		$this->assertFalse( WC_Payments_Features::are_payments_enabled() );
	}

	public function test_is_woopay_enabled_returns_true() {
		add_filter(
			'pre_option__wcpay_feature_woopay_express_checkout',
			function ( $pre_option, $option, $default ) {
				return '1';
			},
			10,
			3
		);
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->assertTrue( WC_Payments_Features::is_woopay_enabled() );
	}

	public function test_is_woopay_enabled_returns_false_when_express_checkout_flag_is_false() {
		add_filter(
			'pre_option__wcpay_feature_woopay_express_checkout',
			function ( $pre_option, $option, $default ) {
				return '0';
			},
			10,
			3
		);
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->assertFalse( WC_Payments_Features::is_woopay_enabled() );
	}

	public function test_is_woopay_enabled_returns_false_when_platform_checkout_flag_is_false() {
		add_filter(
			'pre_option__wcpay_feature_woopay_express_checkout',
			function ( $pre_option, $option, $default ) {
				return '1';
			},
			10,
			3
		);
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'no' );
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->assertFalse( WC_Payments_Features::is_woopay_enabled() );
	}

	public function test_is_woopay_enabled_returns_false_when_ineligible() {
		add_filter(
			'pre_option__wcpay_feature_woopay_express_checkout',
			function ( $pre_option, $option, $default ) {
				return '1';
			},
			10,
			3
		);
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => false ] );
		$this->assertFalse( WC_Payments_Features::is_woopay_enabled() );
	}

	public function test_is_woopay_express_checkout_enabled_returns_true() {
		add_filter(
			'pre_option__wcpay_feature_woopay_express_checkout',
			function ( $pre_option, $option, $default ) {
				return '1';
			},
			10,
			3
		);
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->assertTrue( WC_Payments_Features::is_woopay_express_checkout_enabled() );
	}

	public function test_is_woopay_express_checkout_enabled_returns_false_when_flag_is_false() {
		add_filter(
			'pre_option__wcpay_feature_woopay_express_checkout',
			function ( $pre_option, $option, $default ) {
				return '0';
			},
			10,
			3
		);
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->assertFalse( WC_Payments_Features::is_woopay_express_checkout_enabled() );
	}

	public function test_is_woopay_express_checkout_enabled_returns_false_when_woopay_eligible_is_false() {
		add_filter(
			'pre_option_' . WC_Payments_Features::PROGRESSIVE_ONBOARDING_FLAG_NAME,
			function ( $pre_option, $option, $default ) {
				return '1';
			},
			10,
			3
		);
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => false ] );
		$this->assertFalse( WC_Payments_Features::is_woopay_express_checkout_enabled() );
	}

	public function test_is_progressive_onboarding_enabled_returns_true() {
		add_filter(
			'pre_option_' . WC_Payments_Features::PROGRESSIVE_ONBOARDING_FLAG_NAME,
			function ( $pre_option, $option, $default ) {
				return '1';
			},
			10,
			3
		);
		$this->assertTrue( WC_Payments_Features::is_progressive_onboarding_enabled() );
	}

	public function test_is_progressive_onboarding_enabled_returns_false_when_flag_is_false() {
		add_filter(
			'pre_option_' . WC_Payments_Features::PROGRESSIVE_ONBOARDING_FLAG_NAME,
			function ( $pre_option, $option, $default ) {
				return '0';
			},
			10,
			3
		);
		$this->assertFalse( WC_Payments_Features::is_progressive_onboarding_enabled() );
		$this->assertArrayNotHasKey( 'progressiveOnboarding', WC_Payments_Features::to_array() );
	}

	public function test_is_progressive_onboarding_enabled_returns_false_when_flag_is_not_set() {
		$this->assertFalse( WC_Payments_Features::is_progressive_onboarding_enabled() );
	}

	public function test_split_upe_disabled_with_ineligible_merchant() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'capabilities' => [ 'sepa_debit_payments' => 'active' ] ] );
		add_filter(
			'pre_option_' . WC_Payments_Features::UPE_FLAG_NAME,
			function ( $pre_option, $option, $default ) {
				return '0';
			},
			10,
			3
		);
		add_filter(
			'pre_option_' . WC_Payments_Features::UPE_SPLIT_FLAG_NAME,
			function ( $pre_option, $option, $default ) {
				return '0';
			},
			10,
			3
		);
		add_filter(
			'pre_option_' . WC_Payments_Features::UPE_DEFERRED_INTENT_FLAG_NAME,
			function ( $pre_option, $option, $default ) {
				return '0';
			},
			10,
			3
		);

		$this->assertFalse( WC_Payments_Features::is_upe_enabled() );
		$this->assertFalse( WC_Payments_Features::is_upe_legacy_enabled() );
		$this->assertFalse( WC_Payments_Features::is_upe_split_enabled() );
		$this->assertFalse( WC_Payments_Features::is_upe_deferred_intent_enabled() );
	}

	public function test_legacy_upe_enabled_with_split_upe_ineligible_merchant() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'capabilities' => [ 'sepa_debit_payments' => 'active' ] ] );
		add_filter(
			'pre_option_' . WC_Payments_Features::UPE_FLAG_NAME,
			function ( $pre_option, $option, $default ) {
				return '0';
			},
			10,
			3
		);
		add_filter(
			'pre_option_' . WC_Payments_Features::UPE_SPLIT_FLAG_NAME,
			function ( $pre_option, $option, $default ) {
				return '1';
			},
			10,
			3
		);
		add_filter(
			'pre_option_' . WC_Payments_Features::UPE_DEFERRED_INTENT_FLAG_NAME,
			function ( $pre_option, $option, $default ) {
				return '1';
			},
			10,
			3
		);

		$this->assertTrue( WC_Payments_Features::is_upe_enabled() );
		$this->assertTrue( WC_Payments_Features::is_upe_legacy_enabled() );
		$this->assertFalse( WC_Payments_Features::is_upe_split_enabled() );
		$this->assertFalse( WC_Payments_Features::is_upe_deferred_intent_enabled() );
	}

	public function test_split_upe_enabled_with_eligible_merchant() {
		$this->mock_cache->method( 'get' )->willReturn( [ 'capabilities' => [ 'sepa_debit_payments' => 'inactive' ] ] );
		add_filter(
			'pre_option_' . WC_Payments_Features::UPE_FLAG_NAME,
			function ( $pre_option, $option, $default ) {
				return '0';
			},
			10,
			3
		);
		add_filter(
			'pre_option_' . WC_Payments_Features::UPE_SPLIT_FLAG_NAME,
			function ( $pre_option, $option, $default ) {
				return '1';
			},
			10,
			3
		);
		add_filter(
			'pre_option_' . WC_Payments_Features::UPE_DEFERRED_INTENT_FLAG_NAME,
			function ( $pre_option, $option, $default ) {
				return '1';
			},
			10,
			3
		);

		$this->assertTrue( WC_Payments_Features::is_upe_enabled() );
		$this->assertFalse( WC_Payments_Features::is_upe_legacy_enabled() );
		$this->assertTrue( WC_Payments_Features::is_upe_split_enabled() );
		$this->assertTrue( WC_Payments_Features::is_upe_deferred_intent_enabled() );
	}

	public function test_is_wcpay_frt_review_feature_active_returns_true() {
		add_filter(
			'pre_option_wcpay_frt_review_feature_active',
			function ( $pre_option, $option, $default ) {
				return '1';
			},
			10,
			3
		);
		$this->assertTrue( WC_Payments_Features::is_frt_review_feature_active() );
		remove_all_filters( 'pre_option_wcpay_frt_review_feature_active' );
	}

	public function test_is_frt_review_feature_active_returns_false_when_flag_is_not_set() {
		$this->assertFalse( WC_Payments_Features::is_frt_review_feature_active() );
	}

	public function test_is_bnpl_affirm_afterpay_enabled_return_true_if_flag_not_present_in_account_cache() {
		$account_service_mock = $this->getMockBuilder( WC_Payments_Account::class )
			->disableOriginalConstructor()
			->getMock();

		$account_service_mock
			->expects( $this->once() )
			->method( 'get_cached_account_data' )
			->willReturn( [] );

		WC_Payments::set_account_service( $account_service_mock );

		$this->assertTrue( WC_Payments_Features::is_bnpl_affirm_afterpay_enabled() );
	}

	public function test_is_bnpl_affirm_afterpay_enabled_return_true_if_flag_is_enabled_in_account_cache() {
		$account_service_mock = $this->getMockBuilder( WC_Payments_Account::class )
			->disableOriginalConstructor()
			->getMock();

		$account_service_mock
			->expects( $this->once() )
			->method( 'get_cached_account_data' )
			->willReturn( [ 'is_bnpl_affirm_afterpay_enabled' => true ] );

		WC_Payments::set_account_service( $account_service_mock );

		$this->assertTrue( WC_Payments_Features::is_bnpl_affirm_afterpay_enabled() );
	}

	public function test_is_bnpl_affirm_afterpay_enabled_return_false_if_flag_is_disabled_in_account_cache() {
		$account_service_mock = $this->getMockBuilder( WC_Payments_Account::class )
			->disableOriginalConstructor()
			->getMock();

		$account_service_mock
			->expects( $this->once() )
			->method( 'get_cached_account_data' )
			->willReturn( [ 'is_bnpl_affirm_afterpay_enabled' => false ] );

		WC_Payments::set_account_service( $account_service_mock );

		$this->assertFalse( WC_Payments_Features::is_bnpl_affirm_afterpay_enabled() );
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
