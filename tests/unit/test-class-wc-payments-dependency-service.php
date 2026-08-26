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

	public function test_get_invalid_dependencies_ignores_cached_account_connection() {
		update_option( WCPay\Database_Cache::ACCOUNT_KEY, [ 'data' => [ 'account_id' => 'acct_123' ] ] );

		$dependency_service = $this->getMockBuilder( WC_Payments_Dependency_Service::class )
			->setConstructorArgs( [] )
			->setMethodsExcept( [ 'get_invalid_dependencies' ] )
			->getMock();

		$dependency_service->method( 'is_woo_core_active' )->willReturn( true );
		$dependency_service->method( 'is_woo_core_version_compatible' )->willReturn( false );
		$dependency_service->method( 'is_wc_admin_enabled' )->willReturn( true );
		$dependency_service->method( 'is_wc_admin_version_compatible' )->willReturn( true );
		$dependency_service->method( 'is_wp_version_compatible' )->willReturn( true );

		// The removed second argument used to skip version checks for connected accounts.
		$invalid_deps = $dependency_service->get_invalid_dependencies( true );

		$this->assertEquals( [ 'woocore_outdated' ], $invalid_deps );

		delete_option( WCPay\Database_Cache::ACCOUNT_KEY );
	}

	public function test_get_blocking_dependencies_excludes_version_incompatibilities() {
		$dependency_service = $this->getMockBuilder( WC_Payments_Dependency_Service::class )
			->setConstructorArgs( [] )
			->setMethodsExcept( [ 'get_blocking_dependencies', 'get_invalid_dependencies' ] )
			->getMock();

		$dependency_service->method( 'is_woo_core_active' )->willReturn( true );
		$dependency_service->method( 'is_woo_core_version_compatible' )->willReturn( false );
		$dependency_service->method( 'is_wc_admin_enabled' )->willReturn( true );
		$dependency_service->method( 'is_wc_admin_version_compatible' )->willReturn( false );
		$dependency_service->method( 'is_wp_version_compatible' )->willReturn( false );

		$this->assertEquals( [], $dependency_service->get_blocking_dependencies() );
	}

	public function test_get_blocking_dependencies_includes_missing_woo_core() {
		$dependency_service = $this->getMockBuilder( WC_Payments_Dependency_Service::class )
			->setConstructorArgs( [] )
			->setMethodsExcept( [ 'get_blocking_dependencies', 'get_invalid_dependencies' ] )
			->getMock();

		$dependency_service->method( 'is_woo_core_active' )->willReturn( false );
		$dependency_service->method( 'is_woo_core_version_compatible' )->willReturn( true );
		$dependency_service->method( 'is_wc_admin_enabled' )->willReturn( true );
		$dependency_service->method( 'is_wc_admin_version_compatible' )->willReturn( true );
		$dependency_service->method( 'is_wp_version_compatible' )->willReturn( true );

		$this->assertEquals( [ 'woocore_disabled' ], $dependency_service->get_blocking_dependencies() );
	}

	public function test_display_admin_notices_renders_version_incompatibility_as_warning() {
		$dependency_service = $this->getMockBuilder( WC_Payments_Dependency_Service::class )
			->setConstructorArgs( [] )
			->setMethodsExcept( [ 'display_admin_notices' ] )
			->getMock();

		$dependency_service->method( 'are_assets_built' )->willReturn( true );
		$dependency_service
			->method( 'get_invalid_dependencies' )
			->willReturn( [ WC_Payments_Dependency_Service::WOOCORE_INCOMPATIBLE ] );

		ob_start();
		$dependency_service->display_admin_notices();
		$result = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $result );
		$this->assertStringNotContainsString( 'notice-error', $result );
	}

	public function test_display_admin_notices_renders_blocking_dependency_as_error() {
		$dependency_service = $this->getMockBuilder( WC_Payments_Dependency_Service::class )
			->setConstructorArgs( [] )
			->setMethodsExcept( [ 'display_admin_notices' ] )
			->getMock();

		$dependency_service->method( 'are_assets_built' )->willReturn( true );
		$dependency_service
			->method( 'get_invalid_dependencies' )
			->willReturn( [ WC_Payments_Dependency_Service::WOOCORE_INCOMPATIBLE, WC_Payments_Dependency_Service::WOOCORE_NOT_FOUND ] );

		ob_start();
		$dependency_service->display_admin_notices();
		$result = ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $result );
		$this->assertStringContainsString( 'to be installed and active', $result );
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
