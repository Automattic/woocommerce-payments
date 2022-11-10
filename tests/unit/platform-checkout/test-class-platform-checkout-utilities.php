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
class Platform_Checkout_Utilities_Test extends WCPAY_UnitTestCase {
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
			[ true, 'yes', true ],
			[ true, 'no', false ],
			[ false, 'yes', false ],
			[ false, 'no', false ],
		];
	}

	/**
	 * Platform checkout is available if feature flags are enabled.
	 *
	 * @dataProvider should_enable_platform_checkout_data_provider
	 * @return void
	 */
	public function test_should_enable_platform_checkout( $platform_checkout_eligible, $gateway_platform_checkout_enabled, $expected ) {
		$this->set_is_platform_checkout_eligible( $platform_checkout_eligible );

		$this->gateway_mock->expects( $this->once() )
			->method( 'get_option' )
			->with( 'platform_checkout', 'no' )
			->willReturn( $gateway_platform_checkout_enabled );

		$platform_checkout_utilities = new Platform_Checkout_Utilities();
		$actual                      = $platform_checkout_utilities->should_enable_platform_checkout( $this->gateway_mock );
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
}
