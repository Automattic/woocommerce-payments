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

	/**
	 * Mock WC_Payments_Account.
	 *
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_wcpay_account;

	const FLAG_OPTION_NAME_TO_FRONTEND_KEY_MAPPING = [
		'_wcpay_feature_customer_multi_currency' => 'multiCurrency',
		'_wcpay_feature_documents'               => 'documents',
		'_wcpay_feature_auth_and_capture'        => 'isAuthAndCaptureEnabled',
		'_wcpay_feature_stripe_ece'              => 'isStripeEceEnabled',
	];

	public function set_up() {
		// Mock the main class's cache service.
		$this->_cache     = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );

		// Mock the WCPay Account class to make sure the account is not restricted by default.
		$this->mock_wcpay_account = $this->createMock( WC_Payments_Account::class );
		$this->mock_wcpay_account
			->method( 'is_account_rejected' )
			->willReturn( false );
		$this->mock_wcpay_account
			->method( 'is_account_under_review' )
			->willReturn( false );

		WC_Payments::set_account_service( $this->mock_wcpay_account );
	}

	public function tear_down() {
		$reflection   = new ReflectionClass( WC_Payments_Features::class );
		$constants    = $reflection->getConstants();
		$option_array = array_filter(
			$constants,
			function ( $key ) {
				return strpos( $key, '_wcpay_feature_' ) === 0;
			},
			ARRAY_FILTER_USE_KEY
		);

		$this->clear_feature_flag_options( $option_array );
		$this->clear_feature_flag_options( array_keys( self::FLAG_OPTION_NAME_TO_FRONTEND_KEY_MAPPING ) );

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
		$this->assertTrue( WC_Payments_Features::is_customer_multi_currency_enabled() );
	}

	public function test_customer_multi_currency_can_be_disabled() {
		$this->set_feature_flag_option( '_wcpay_feature_customer_multi_currency', '0' );
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

	public function test_is_woopay_eligible_when_account_is_suspended_returns_false() {
		$mock_wcpay_account = $this->createMock( WC_Payments_Account::class );
		$mock_wcpay_account
			->method( 'is_account_under_review' )
			->willReturn( true );

		WC_Payments::set_account_service( $mock_wcpay_account );

		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );

		$this->assertFalse( WC_Payments_Features::is_woopay_eligible() );
	}

	public function test_is_woopay_eligible_when_account_is_rejected_returns_false() {
		$mock_wcpay_account = $this->createMock( WC_Payments_Account::class );
		$mock_wcpay_account
			->method( 'is_account_rejected' )
			->willReturn( true );

		WC_Payments::set_account_service( $mock_wcpay_account );

		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );

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
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME, '1' );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->assertTrue( WC_Payments_Features::is_woopay_enabled() );
	}

	public function test_is_woopay_enabled_returns_false_when_express_checkout_flag_is_false() {
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME, '0' );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->assertFalse( WC_Payments_Features::is_woopay_enabled() );
	}

	public function test_is_woopay_enabled_returns_false_when_platform_checkout_flag_is_false() {
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME, '1' );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'no' );
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->assertFalse( WC_Payments_Features::is_woopay_enabled() );
	}

	public function test_is_woopay_enabled_returns_false_when_ineligible() {
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME, '1' );
		WC_Payments::get_gateway()->update_option( 'platform_checkout', 'yes' );
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => false ] );
		$this->assertFalse( WC_Payments_Features::is_woopay_enabled() );
	}

	public function test_is_woopay_express_checkout_enabled_returns_true() {
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME, '1' );
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->assertTrue( WC_Payments_Features::is_woopay_express_checkout_enabled() );
	}

	public function test_is_woopay_express_checkout_enabled_returns_false_when_flag_is_false() {
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME, '0' );
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => true ] );
		$this->assertFalse( WC_Payments_Features::is_woopay_express_checkout_enabled() );
	}

	public function test_is_woopay_express_checkout_enabled_returns_false_when_woopay_eligible_is_false() {
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME, '1' );
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => false ] );
		$this->assertFalse( WC_Payments_Features::is_woopay_express_checkout_enabled() );
	}

	public function test_is_woopay_direct_checkout_enabled_returns_true() {
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME, '1' );
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_DIRECT_CHECKOUT_FLAG_NAME, '1' );
		$this->mock_cache->method( 'get' )->willReturn(
			[
				'platform_checkout_eligible'        => true,
				'platform_direct_checkout_eligible' => true,
			]
		);
		$this->assertTrue( WC_Payments_Features::is_woopay_direct_checkout_enabled() );
	}

	public function test_is_woopay_direct_checkout_enabled_returns_false_when_flag_is_false() {
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME, '1' );
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_DIRECT_CHECKOUT_FLAG_NAME, '0' );
		$this->mock_cache->method( 'get' )->willReturn(
			[
				'platform_checkout_eligible'        => true,
				'platform_direct_checkout_eligible' => false,
			]
		);
		$this->assertFalse( WC_Payments_Features::is_woopay_direct_checkout_enabled() );
	}

	public function test_is_woopay_direct_checkout_enabled_returns_false_when_woopay_eligible_is_false() {
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME, '1' );
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_DIRECT_CHECKOUT_FLAG_NAME, '1' );
		$this->mock_cache->method( 'get' )->willReturn(
			[
				'platform_checkout_eligible'        => false,
				'platform_direct_checkout_eligible' => true,
			]
		);
		$this->assertFalse( WC_Payments_Features::is_woopay_direct_checkout_enabled() );
	}

	public function test_is_woopay_direct_checkout_enabled_returns_true_when_first_party_auth_is_disabled() {
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_EXPRESS_CHECKOUT_FLAG_NAME, '1' );
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_FIRST_PARTY_AUTH_FLAG_NAME, '0' );
		$this->set_feature_flag_option( WC_Payments_Features::WOOPAY_DIRECT_CHECKOUT_FLAG_NAME, '1' );
		$this->mock_cache->method( 'get' )->willReturn(
			[
				'platform_checkout_eligible'        => true,
				'platform_direct_checkout_eligible' => true,
			]
		);
		$this->assertTrue( WC_Payments_Features::is_woopay_direct_checkout_enabled() );
	}

	public function test_is_wcpay_frt_review_feature_active_returns_true() {
		$this->set_feature_flag_option( 'wcpay_frt_review_feature_active', '1' );
		$this->assertTrue( WC_Payments_Features::is_frt_review_feature_active() );
		$this->clear_feature_flag_options( [ 'wcpay_frt_review_feature_active' ] );
	}

	public function test_is_frt_review_feature_active_returns_false_when_flag_is_not_set() {
		$this->assertFalse( WC_Payments_Features::is_frt_review_feature_active() );
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

	private function set_feature_flag_option( string $option, string $value ) {
		add_filter(
			'pre_option_' . $option,
			function () use ( $value ) {
				return $value;
			}
		);
	}

	private function clear_feature_flag_options( array $option_array ) {
		foreach ( $option_array as $option ) {
			remove_all_filters( 'pre_option_' . $option );
		}
	}
}
