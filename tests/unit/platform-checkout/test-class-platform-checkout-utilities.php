<?php
/**
 * Class Platform_Checkout_Utilities_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Platform_Checkout\Platform_Checkout_Utilities;

/**
 * Platform_Checkout_Utilities unit tests.
 */
class Platform_Checkout_Utilities_Test extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();
		$this->gateway_mock = $this->createMock( WC_Payment_Gateway_WCPay::class );

		// Mock the main class's cache service.
		$this->_cache     = WC_Payments::get_database_cache();
		$this->mock_cache = $this->createMock( WCPay\Database_Cache::class );
		WC_Payments::set_database_cache( $this->mock_cache );
	}

	public function tear_down() {
		// Restore the cache service in the main class.
		WC_Payments::set_database_cache( $this->_cache );
		parent::tear_down();
	}

	/**
	 * Data provider for test_should_enable_platform_checkout.
	 *
	 * @return boolean
	 */
	public function should_enable_platform_checkout_data_provider() {
		return [
			[ true, 'yes', true, false ],
			[ true, 'yes', false, true ],
			[ true, 'no', true, false ],
			[ true, 'no', false, false ],
			[ false, 'yes', true, false ],
			[ false, 'yes', false, false ],
			[ false, 'no', true, false ],
			[ false, 'no', false, false ],
		];
	}

	/**
	 * Platform checkout is available if feature flags are enabled and if there is no subscription product in cart.
	 *
	 * @dataProvider should_enable_platform_checkout_data_provider
	 * @return void
	 */
	public function test_should_enable_platform_checkout( $platform_checkout_eligible, $gateway_platform_checkout_enabled, $is_subscription_in_cart, $expected ) {
		$this->set_is_platform_checkout_eligible( $platform_checkout_eligible );

		$this->gateway_mock->expects( $this->once() )
			->method( 'get_option' )
			->with( 'platform_checkout', 'no' )
			->willReturn( $gateway_platform_checkout_enabled );

		$platform_checkout_utilities = $this->createWithStubs( $is_subscription_in_cart );

		$actual = $platform_checkout_utilities->should_enable_platform_checkout( $this->gateway_mock );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Cache account details.
	 *
	 * @param $account
	 */
	private function set_is_platform_checkout_eligible( $is_platform_checkout_eligible ) {
		$this->mock_cache->method( 'get' )->willReturn( [ 'platform_checkout_eligible' => $is_platform_checkout_eligible ] );
	}

	/**
	 * Return a Platform_Checkout_Utilities object with stubbed trait for is_subscription_item_in_cart().
	 *
	 * @param boolean $is_subscription_item_in_cart True if any subscription product is in cart.
	 * @return Platform_Checkout_Utilities
	 */
	private function createWithStubs( $is_subscription_item_in_cart ) {
		$platform_checkout_utilities = $this->getMockBuilder( Platform_Checkout_Utilities::class )
			->setMethods( [ 'is_subscription_item_in_cart' ] )
			->getMock();
		$platform_checkout_utilities->expects( $this->once() )
			->method( 'is_subscription_item_in_cart' )
			->willReturn( $is_subscription_item_in_cart );

		return $platform_checkout_utilities;
	}
}
