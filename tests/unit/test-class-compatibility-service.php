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

	/**
	 * Tests to make sure filters are registered correctly.
	 *
	 * @param string $filter            The filter name.
	 * @param string $method            The method being called in the class.
	 * @param int    $expected_priority The expected priority.
	 *
	 * @dataProvider provider_test_registers_woocommerce_filters_properly
	 *
	 * @return void
	 */
	public function test_registers_woocommerce_filters_properly( string $filter, string $method, int $expected_priority ) {
		$priority = has_filter( $filter, [ $this->compatibility_service, $method ] );
		$this->assertEquals( $expected_priority, $priority );
	}

	public function provider_test_registers_woocommerce_filters_properly(): array {
		return [
			'woocommerce_payments_account_refreshed' => [
				'filter'   => 'woocommerce_payments_account_refreshed',
				'method'   => 'update_compatibility_data',
				'priority' => 10,
			],
			'wc_payments_get_onboarding_data_args'   => [
				'filter'   => 'wc_payments_get_onboarding_data_args',
				'method'   => 'add_compatibility_onboarding_data',
				'priority' => 10,
			],
		];
	}

	public function test_get_compatibility_data() {
		// Arrange: Create the expected value.
		$expected = $this->get_mock_compatibility_data();

		// Act/Assert: Call the method we're testing and confirm we get the expected value.
		$this->assertSame( $expected, $this->compatibility_service->get_compatibility_data() );
	}

	public function test_update_compatibility_data() {
		// Arrange: Create the expected value to be passed to update_compatibility_data.
		$expected = $this->get_mock_compatibility_data();

		// Arrange/Assert: Set the expectations for update_compatibility_data.
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'update_compatibility_data' )
			->with( $expected );

		// Act: Call the method we're testing.
		$this->compatibility_service->update_compatibility_data();
	}

	public function test_add_compatibility_onboarding_data() {
		// Arrange: Create the expected value.
		$expected = [ 'compatibility_data' => $this->get_mock_compatibility_data() ];

		// Act/Assert: Call the method we're testing and confirm we get the expected value.
		$this->assertSame( $expected, $this->compatibility_service->add_compatibility_onboarding_data( [] ) );
	}

	/**
	 * Returns the mock compatibility data.
	 *
	 * @param array $args If any values need to be overridden, the values can be added here.
	 *
	 * @return array
	 */
	private function get_mock_compatibility_data( array $args = [] ): array {
		return array_merge(
			[
				'woopayments_version' => WCPAY_VERSION_NUMBER,
				'woocommerce_version' => WC_VERSION,
			],
			$args
		);
	}
}
