<?php
/**
 * Class API_Mode_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\API\Mode as API_Class;

// phpcs:disable Generic.Files.OneObjectStructurePerFile

/**
 * Extend the `Mode` class to allow `is_wcpay_dev_mode_defined` to be overwritten.
 */
class Mode extends API_Class {
	protected static function is_wcpay_dev_mode_defined() {
		return API_Mode_Test::$is_wcpay_dev_mode_defined;
	}

	protected static function wp_get_environment_type() {
		return API_Mode_Test::$wp_environment_type;
	}
}

/**
 * WCPay\API\Mode unit tests.
 */
class API_Mode_Test extends WCPAY_UnitTestCase {
	/**
	 * Used by the mock class to check if constants are defined.
	 *
	 * @var bool
	 */
	public static $is_wcpay_dev_mode_defined = false;

	/**
	 * Used by the mock class to check for WP's environment.
	 *
	 * @var string
	 */
	public static $wp_environment_type = 'production';

	public function tearDown() : void {
		// Remove all filters.
		remove_filter( 'wcpay_dev_mode', '__return_true' );
		remove_filter( 'wcpay_test_mode', '__return_true' );
		remove_filter( 'wcpay_dev_mode', '__return_null' );
		remove_filter( 'wcpay_test_mode', '__return_null' );

		// Reset the state of the gatway and the whole plugin.
		WC_Payments::get_gateway()->update_option( 'test_mode', 'no' );
		Mode::enter_live_mode();

		parent::tearDown();
	}

	public function test_init_defaults_to_live_mode() {
		$this->assertTrue( Mode::is_live() );
	}

	public function test_init_enters_dev_mode_when_constant_is_defined() {
		// Simulate that `WCPAY_DEV_MODE` is defined and true.
		self::$is_wcpay_dev_mode_defined = true;
		$this->reload();

		$this->assertTrue( Mode::is_dev() );
		$this->assertTrue( Mode::is_test() );
		$this->assertFalse( Mode::is_live() );

		// Cleanup.
		self::$is_wcpay_dev_mode_defined = false;
	}

	public function test_init_enters_dev_mode_through_filter() {
		// Force dev mode to be entered through the filter.
		add_filter( 'wcpay_dev_mode', '__return_true' );

		$this->reload();
		$this->assertTrue( Mode::is_dev() );
	}

	public function test_init_enters_test_mode_with_gateway_test_mode_settings() {
		$gateway = WC_Payments::get_gateway();

		// Set up test mode through settings.
		$gateway->update_option( 'test_mode', 'yes' );

		// Reset and check.
		$this->reload();
		$this->assertFalse( Mode::is_dev() );
		$this->assertTrue( Mode::is_test() );
	}

	public function test_init_enters_test_mode_through_filter() {
		// FOrce test mode to be entered through the filter.
		add_filter( 'wcpay_test_mode', '__return_true' );

		$this->reload();
		$this->assertTrue( Mode::is_test() );
		$this->assertFalse( Mode::is_dev() );
	}

	public function test_init_test_init_enters_dev_mode_when_environment_is_dev() {
		self::$wp_environment_type = 'development';

		$this->reload();
		$this->assertTrue( Mode::is_dev() );

		self::$wp_environment_type = 'production';
	}

	public function test_throw_exception_if_uninitialized() {
		add_filter( 'wcpay_dev_mode', '__return_null' );
		add_filter( 'wcpay_test_mode', '__return_null' );
		$this->reload();

		$this->expectException( Exception::class );
		Mode::is_live();

		remove_filter( 'wcpay_dev_mode', '__return_null' );
		remove_filter( 'wcpay_test_mode', '__return_null' );
	}

	private function reload() {
		Mode::init( WC_Payments::get_gateway() );
	}
}

// phpcs:enable Generic.Files.OneObjectStructurePerFile
