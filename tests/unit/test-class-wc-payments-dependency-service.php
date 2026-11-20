<?php
/**
 * These tests make assertions against class WC_Payments_Dependency_Service_Test.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Dependency_Service_Test class.
 */
class WC_Payments_Dependency_Service_Test extends WCPAY_UnitTestCase {

	/**
	 * Sets up things all tests need.
	 */
	public function set_up() {
		parent::set_up();

		$this->dependency_service = new WC_Payments_Dependency_Service();
	}

	public function test_get_invalid_dependencies() {

		// Create a partial mock, leaving out the method under test.
		$dependency_service = $this->getMockBuilder( WC_Payments_Dependency_Service::class )
			->setConstructorArgs( [] )
			->setMethodsExcept( [ 'get_invalid_dependencies' ] )
			->getMock();

		// Mock the is_ functions.
		$dependency_service
			->expects( $this->once() )
			->method( 'is_woo_core_active' )
			->willReturn( false );
		$dependency_service
			->expects( $this->once() )
			->method( 'is_woo_core_version_compatible' )
			->willReturn( true );
		$dependency_service
			->expects( $this->once() )
			->method( 'is_wc_admin_enabled' )
			->willReturn( false );
		$dependency_service
			->expects( $this->once() )
			->method( 'is_wc_admin_version_compatible' )
			->willReturn( true );
		$dependency_service
			->expects( $this->once() )
			->method( 'is_wp_version_compatible' )
			->willReturn( false );

		// Call the unmocked method.
		$invalid_deps = $dependency_service->get_invalid_dependencies();

		// Perform assertions...
		$this->assertIsArray( $invalid_deps );
		$this->assertEquals( 3, count( $invalid_deps ) );
		$this->assertContains( WC_Payments_Dependency_Service::WOOCORE_NOT_FOUND, $invalid_deps );
		$this->assertNotContains( WC_Payments_Dependency_Service::WOOCORE_INCOMPATIBLE, $invalid_deps );
		$this->assertContains( WC_Payments_Dependency_Service::WOOADMIN_NOT_FOUND, $invalid_deps );
		$this->assertNotContains( WC_Payments_Dependency_Service::WOOADMIN_INCOMPATIBLE, $invalid_deps );
		$this->assertContains( WC_Payments_Dependency_Service::WP_INCOMPATIBLE, $invalid_deps );
	}

	public function test_display_admin_notices() {

		// Create a partial mock, leaving out the method under test.
		$dependency_service = $this->getMockBuilder( WC_Payments_Dependency_Service::class )
			->setConstructorArgs( [] )
			->setMethodsExcept( [ 'display_admin_notices' ] )
			->getMock();

		$dependency_service
			->expects( $this->once() )
			->method( 'get_invalid_dependencies' )
			->willReturn( [ WC_Payments_Dependency_Service::WOOADMIN_NOT_FOUND, WC_Payments_Dependency_Service::WP_INCOMPATIBLE ] );

		// Call the unmocked method.
		ob_start();
		$dependency_service->display_admin_notices();
		$result = ob_get_clean();

		// Perform assertions...
		$this->assertIsString( $result );
		$this->assertStringContainsStringIgnoringCase( 'WooPayments requires WooCommerce Admin to be enabled', $result );
	}

	/**
	 * @dataProvider blocking_dependencies_provider
	 */
	public function test_get_blocking_dependencies( $invalid_dependencies, $expected ) {
		$service = $this->getMockBuilder( WC_Payments_Dependency_Service::class )
			->setMethods( [ 'get_invalid_dependencies' ] )
			->getMock();
		$service->method( 'get_invalid_dependencies' )->willReturn( $invalid_dependencies );

		$this->assertSame( $expected, $service->get_blocking_dependencies() );
	}

	public function blocking_dependencies_provider() {
		return [
			'compatible'        => [ [], [] ],
			'old versions'      => [ [ 'woocore_outdated', 'wc_admin_outdated', 'wp_outdated' ], [] ],
			'missing Woo'       => [ [ 'woocore_disabled', 'woocore_outdated', 'wc_admin_not_found' ], [ 'woocore_disabled', 'wc_admin_not_found' ] ],
			'disabled WC Admin' => [ [ 'woocore_outdated', 'wc_admin_not_found' ], [ 'wc_admin_not_found' ] ],
		];
	}

	public function test_account_cache_clear_does_not_change_blocking_dependencies() {
		$service = $this->getMockBuilder( WC_Payments_Dependency_Service::class )
			->setMethods( [ 'is_woo_core_version_compatible' ] )
			->getMock();
		$service->method( 'is_woo_core_version_compatible' )->willReturn( false );
		update_option( WCPay\Database_Cache::ACCOUNT_KEY, [ 'data' => [ 'account_id' => 'acct_test' ] ] );

		$this->assertSame( [], $service->get_blocking_dependencies() );
		WC_Payments::get_account_service()->clear_cache();

		$this->assertFalse( get_option( 'wcpay_account_data' ) );
		$this->assertSame( [], $service->get_blocking_dependencies() );
		$this->assertSame( [ 'woocore_outdated' ], $service->get_invalid_dependencies() );
	}

	/**
	 * @dataProvider dependency_notice_provider
	 */
	public function test_dependency_notice_severity( $dependencies, $notice_class, $message ) {
		$service = $this->getMockBuilder( WC_Payments_Dependency_Service::class )
			->setMethods( [ 'get_invalid_dependencies', 'are_assets_built' ] )
			->getMock();
		$service->method( 'get_invalid_dependencies' )->willReturn( $dependencies );
		$service->method( 'are_assets_built' )->willReturn( true );

		ob_start();
		$service->display_admin_notices();
		$notice = ob_get_clean();

		$this->assertStringContainsString( $notice_class, $notice );
		$this->assertStringContainsString( $message, $notice );
	}

	public function dependency_notice_provider() {
		return [
			'old Woo'           => [ [ 'woocore_outdated' ], 'notice-warning', 'WooCommerce' ],
			'old WP'            => [ [ 'wp_outdated' ], 'notice-warning', 'WordPress' ],
			'old WC Admin'      => [ [ 'wc_admin_outdated' ], 'notice-warning', 'WooCommerce Admin' ],
			'missing Woo'       => [ [ 'woocore_disabled', 'woocore_outdated' ], 'notice-error', 'to be installed and active' ],
			'disabled WC Admin' => [ [ 'woocore_outdated', 'wc_admin_not_found' ], 'notice-error', 'WooCommerce Admin to be enabled' ],
		];
	}

	public function test_display_admin_notices_assets_not_built() {
		// Create a partial mock, leaving out the method under test.
		$dependency_service = $this->getMockBuilder( WC_Payments_Dependency_Service::class )
			->setConstructorArgs( [] )
			->setMethodsExcept( [ 'display_admin_notices' ] )
			->getMock();

		$dependency_service
			->expects( $this->once() )
			->method( 'are_assets_built' )
			->willReturn( false );

		// Call the unmocked method.
		ob_start();
		$dependency_service->display_admin_notices();
		$result = ob_get_clean();

		// Perform assertions...
		$this->assertIsString( $result );
		$this->assertStringContainsStringIgnoringCase( 'You have installed a development version of WooPayments which requires files to be built', $result );
	}
}
