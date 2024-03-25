<?php
/**
 * Class Core_Mode_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Core\Mode;

/**
 * WCPay\Core\Mode unit tests.
 */
class Core_Mode_Test extends WCPAY_UnitTestCase {
	/**
	 * Holds the main class.
	 *
	 * @var Mode
	 */
	protected $mode;

	/**
	 * Holds the mock gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	protected $mock_gateway;

	public function setUp() : void {
		parent::setUp();

		$this->mode = $this->getMockBuilder( Mode::class )
			->setMethods( [ 'is_wcpay_dev_mode_defined', 'get_wp_environment_type' ] )
			->getMock();
	}

	public function tearDown() : void {
		remove_filter( 'wcpay_dev_mode', '__return_true' );
		remove_filter( 'wcpay_test_mode', '__return_true' );

		parent::tearDown();
	}

	public function test_init_defaults_to_live_mode() {
		update_option( 'woocommerce_woocommerce_payments_settings', [ 'test_mode' => 'no' ] );

		$this->assertTrue( $this->mode->is_live() );
	}

	public function test_init_enters_dev_mode_when_constant_is_defined() {
		$this->mode->expects( $this->once() )
			->method( 'is_wcpay_dev_mode_defined' )
			->willReturn( true );

		$this->assertTrue( $this->mode->is_dev() );
		$this->assertTrue( $this->mode->is_test() );
		$this->assertFalse( $this->mode->is_live() );
	}

	public function test_init_enters_dev_mode_through_filter() {
		// Force dev mode to be entered through the filter.
		add_filter( 'wcpay_dev_mode', '__return_true' );
		$this->assertTrue( $this->mode->is_dev() );
	}

	public function test_init_enters_test_mode_with_gateway_test_mode_settings() {
		update_option( 'woocommerce_woocommerce_payments_settings', [ 'test_mode' => 'yes' ] );

		// Reset and check.
		$this->assertFalse( $this->mode->is_dev() );
		$this->assertTrue( $this->mode->is_test() );
	}

	public function test_init_enters_test_mode_through_filter() {
		// FOrce test mode to be entered through the filter.
		add_filter( 'wcpay_test_mode', '__return_true' );

		$this->assertTrue( $this->mode->is_test() );
		$this->assertFalse( $this->mode->is_dev() );
	}

	public function test_init_test_init_enters_dev_mode_when_environment_is_dev() {
		$this->mode->expects( $this->once() )
			->method( 'get_wp_environment_type' )
			->willReturn( 'development' );

		$this->assertTrue( $this->mode->is_dev() );
	}
}
