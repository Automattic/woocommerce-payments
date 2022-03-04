<?php
/**
 * Class WC_Payments_Subscription_Minimum_Amount_Handler_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Subscription_Minimum_Amount_Handler unit tests.
 */
class WC_Payments_Subscription_Minimum_Amount_Handler_Test extends WP_UnitTestCase {

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * WC_Payments_Subscription_Minimum_Amount_Handler object.
	 *
	 * @var WC_Payments_Subscription_Minimum_Amount_Handler
	 */
	private $minimum_amount_handler;

	/**
	 * Set up the unit tests objects.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client        = $this->createMock( WC_Payments_API_Client::class );
		$this->minimum_amount_handler = new WC_Payments_Subscription_Minimum_Amount_Handler( $this->mock_api_client );
	}

	/**
	 * WC_Payments_Subscription_Minimum_Amount_Handler::get_minimum_recurring_amount tests
	 */
	public function test_get_minimum_recurring_amount() {
		$usd_minimum_amount = 1.00; // $1.00
		$gbp_minimum_amount = 0.60; // £0.30
		$filter_value       = false; // The function being tested is a callback and the first param is false by default.

		/**
		 * USD tests
		 */
		$this->mock_api_client
			->expects( $this->exactly( 2 ) ) // We'll test that caching is working by calling it twice.
			->method( 'get_currency_minimum_recurring_amount' )
			->withConsecutive( [ 'usd' ], [ 'gbp' ] )
			->willReturnOnConsecutiveCalls( 100, 60 ); // 100 US cents

		$actual_usd_minimum = $this->minimum_amount_handler->get_minimum_recurring_amount( $filter_value, 'usd' );

		$this->assertSame( $usd_minimum_amount, $actual_usd_minimum );

		// Calling it again to test caching.
		$this->minimum_amount_handler->get_minimum_recurring_amount( $filter_value, 'usd' );

		// Test capitalized currency codes.
		$actual_usd_minimum = $this->minimum_amount_handler->get_minimum_recurring_amount( $filter_value, 'USD' );

		$this->assertSame( $usd_minimum_amount, $actual_usd_minimum );

		/**
		 * GBP tests
		 */
		$actual_gbp_minimum = $this->minimum_amount_handler->get_minimum_recurring_amount( $filter_value, 'gbp' );

		$this->assertSame( $gbp_minimum_amount, $actual_gbp_minimum );
	}
}
