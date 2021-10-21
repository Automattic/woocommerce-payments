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

	/**
	 * 1. test dependencies
	 * 2. test get invalid dependencies
	 */

	public function test_woocommerce_active_check() {

		// loaded on bootstrap.php.
		$this->assertTrue( $this->dependency_service->is_woo_core_active() );
	}

	public function test_individual_checks_return_bool() {
		$this->assertIsBool( $this->dependency_service->is_woo_core_active() );
		$this->assertIsBool( $this->dependency_service->is_woo_core_version_compatible() );
		$this->assertIsBool( $this->dependency_service->is_wc_admin_enabled() );
		$this->assertIsBool( $this->dependency_service->is_wc_admin_version_compatible() );
		$this->assertIsBool( $this->dependency_service->is_wp_version_compatible() );

	}

	public function test_woocommerce_admin_disabled() {

		add_filter( 'woocommerce_admin_disabled', '__return_true' );
		$this->assertFalse( $this->dependency_service->is_wc_admin_enabled() );

		remove_filter( 'woocommerce_admin_disabled', '__return_true' );
		$this->assertTrue( $this->dependency_service->is_wc_admin_enabled() );
	}

	public function test_get_invalid_dependencies_return_array() {
		$this->assertIsArray( $this->dependency_service->get_invalid_dependencies() );
	}

	public function test_has_valid_dependencies_return_bool() {
		$this->assertIsBool( $this->dependency_service->has_valid_dependencies() );
	}

}
