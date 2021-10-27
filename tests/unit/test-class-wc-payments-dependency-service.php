<?php
/**
 * These tests make assertions against class WC_Payments_Dependency_Service_Test.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Dependency_Service_Test class.
 */
class WC_Payments_Dependency_Service_Test extends WP_UnitTestCase {

	/**
	 * Sets up things all tests need.
	 */
	public function setUp() {
		parent::setUp();

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
		$this->assertStringContainsStringIgnoringCase( 'WooCommerce Payments requires WooCommerce Admin to be enabled', $result );

	}

}
