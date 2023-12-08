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
	}

	public function test_update_compatibility_data() {
		// Arrange: Create the expected value to be passed to update_compatibility_data.
		$expected = [
			'woopayments_version' => WCPAY_VERSION_NUMBER,
			'woocommerce_version' => WC_VERSION,
		];

		// Arrange/Assert: Set the expectations for update_compatibility_data.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_compatibility_data' )
			->with( $expected );

		// Act: Call the method we're testing.
		$this->compatibility_service->update_compatibility_data();
	}
}
