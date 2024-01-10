<?php
/**
 * Class Compatibility_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Compatibility_Service;

/**
 * Unit tests related to the Compatibility_Service class.
 */
class Compatibility_Service_Test extends WCPAY_UnitTestCase {
	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Compatibility_Service.
	 *
	 * @var Compatibility_Service
	 */
	private $compatibility_service;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client       = $this->createMock( WC_Payments_API_Client::class );
		$this->compatibility_service = new Compatibility_Service( $this->mock_api_client );
		$this->compatibility_service->init_hooks();
	}

	public function test_registers_woocommerce_filters_properly() {
		$priority = has_filter( 'woocommerce_payments_account_refreshed', [ $this->compatibility_service, 'update_compatibility_data' ] );
		$this->assertEquals( 10, $priority );
		$priority = has_action( 'after_switch_theme', [ $this->compatibility_service, 'update_compatibility_data' ] );
		$this->assertEquals( 10, $priority );
	}

	public function test_update_compatibility_data() {
		$stylesheet = 'my_theme_name';
		add_filter(
			'stylesheet',
			function( $theme ) use ( $stylesheet ) {
				return $stylesheet;
			}
		);

		// Arrange: Create the expected value to be passed to update_compatibility_data.
		$expected = [
			'woopayments_version' => WCPAY_VERSION_NUMBER,
			'woocommerce_version' => WC_VERSION,
			'blog_theme'          => $stylesheet,
			'active_plugins'      => [
				'woocommerce/woocommerce.php',
				'woocommerce-payments/woocommerce-payments.php',
			],
		];

		// Arrange/Assert: Set the expectations for update_compatibility_data.
		add_filter( 'option_active_plugins', [ $this, 'active_plugins_filter_return' ] );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_compatibility_data' )
			->with( $expected );

		// Act: Call the method we're testing.
		$this->compatibility_service->update_compatibility_data();

		remove_filter( 'option_active_plugins', [ $this, 'active_plugins_filter_return' ] );
	}

	public function test_update_compatibility_data_active_plugins_false() {
		$stylesheet = 'my_theme_name';
		add_filter(
			'stylesheet',
			function( $theme ) use ( $stylesheet ) {
				return $stylesheet;
			}
		);

		// Arrange: Create the expected value to be passed to update_compatibility_data.
		$expected = [
			'woopayments_version' => WCPAY_VERSION_NUMBER,
			'woocommerce_version' => WC_VERSION,
			'blog_theme'          => $stylesheet,
			'active_plugins'      => [],
		];

		$this->break_active_plugins_option();

		// Arrange/Assert: Set the expectations for update_compatibility_data.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_compatibility_data' )
			->with( $expected );

		// Act: Call the method we're testing.
		$this->compatibility_service->update_compatibility_data();

		$this->fix_active_plugins_option();
	}


	public function active_plugins_filter_return() {
		return [
			'woocommerce/woocommerce.php',
			'woocommerce-payments/woocommerce-payments.php',
		];
	}

	private function break_active_plugins_option() {
		update_option( 'temp_active_plugins', get_option( 'active_plugins' ) );
		delete_option( 'active_plugins' );
	}

	private function fix_active_plugins_option() {
		update_option( 'active_plugins', get_option( 'temp_active_plugins' ) );
		delete_option( 'temp_active_plugins' );
	}
}
