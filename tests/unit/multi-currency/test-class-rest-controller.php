<?php
/**
 * Class WCPay_Multi_Currency_Rest_Controller_Tests
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\MultiCurrency\RestController;

/**
 * WC_REST_Payments_Tos_Controller unit tests.
 */
class WCPay_Multi_Currency_Rest_Controller_Tests extends WCPAY_UnitTestCase {

	/**
	 * Tested REST route.
	 */
	const ROUTE = '/wc/v3/payments/multi-currency';

	/**
	 * The system under test.
	 *
	 * @var RestController
	 */
	private $controller;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$mock_api_client  = $this->getMockBuilder( WC_Payments_API_Client::class )->disableOriginalConstructor()->getMock();
		$this->controller = new RestController( $mock_api_client );
	}

	public function test_get_store_currencies_gets_expected_response() {
		// Arrange: Create expected response.
		$expected = rest_ensure_response( WC_Payments_Multi_Currency()->get_store_currencies() );

		// Act: Get the store currencies.
		$response = $this->controller->get_store_currencies();

		// Assert: Confirm the response is what we expected.
		$this->assertEquals( $expected, $response );
	}

	/**
	 * This test is not possible due to when MC is initialized it attempts to get the available currencies based on the
	 * WCPay server connection and account currencies. If there is not a valid JP connection, it just returns the store currency.
	 */
	public function test_update_enabled_currencies_updates_currencies() {
		$this->markTestSkipped( 'This test is not able to be completed unless local setups are refactored.' );
		// Arrange: Set currencies for test.
		$enabled_currencies = WC_Payments_Multi_Currency()->get_enabled_currencies();
		$new_currencies     = [ 'USD', 'EUR', 'GBP' ];

		// Arrange: Create expected response.
		update_option( 'wcpay_multi_currency_enabled_currencies', $new_currencies );
		$expected = rest_ensure_response( WC_Payments_Multi_Currency()->get_store_currencies() );

		// Arrange: Reset the enabled currencies.
		update_option( 'wcpay_multi_currency_enabled_currencies', $enabled_currencies );

		// Arrange: Create the new REST request.
		$request = new WP_REST_Request( 'POST', self::ROUTE . '/update-enabled-currencies' );
		$request->set_body_params(
			[
				'enabled' => $new_currencies,
			]
		);

		// Act: Update the enabled currencies.
		$response = $this->controller->update_enabled_currencies( $request );

		// Assert: Confirm the response is what we expected.
		$this->assertEquals( $expected, $response );
	}

	public function test_get_single_currency_settings() {
		// Arrange: Add the single currency settings.
		update_option( 'wcpay_multi_currency_exchange_rate_cad', 'manual' );
		update_option( 'wcpay_multi_currency_manual_rate_cad', 2 );
		update_option( 'wcpay_multi_currency_price_rounding_cad', 1 );
		update_option( 'wcpay_multi_currency_price_charm_cad', 0 );

		// Arrange: Create expected response.
		$expected = rest_ensure_response( WC_Payments_Multi_Currency()->get_single_currency_settings( 'CAD' ) );

		// Arrange: Create the new REST request.
		$request = new WP_REST_Request( 'GET', self::ROUTE . '/currencies/CAD' );
		$request->set_query_params(
			[
				'currency_code' => 'CAD',
			]
		);

		// Act: Get the single currency settings.
		$response = $this->controller->get_single_currency_settings( $request );

		// Assert: Confirm the response is what we expected.
		$this->assertEquals( $expected, $response );
	}

	/**
	 * This test uses USD due to that's the only currency available to test with.
	 * This is due to when MC is initialized it attempts to get the available currencies based on the WCPay server
	 * connection and account currencies. If there is not a valid JP connection, it just returns the store currency.
	 */
	public function test_update_single_currency_settings() {
		// Arrange: Add the single currency settings.
		update_option( 'wcpay_multi_currency_exchange_rate_usd', 'manual' );
		update_option( 'wcpay_multi_currency_manual_rate_usd', 2 );
		update_option( 'wcpay_multi_currency_price_rounding_usd', 1 );
		update_option( 'wcpay_multi_currency_price_charm_usd', 0 );

		// Arrange: Create expected response.
		$expected = rest_ensure_response( WC_Payments_Multi_Currency()->get_single_currency_settings( 'USD' ) );

		// Arrange: Now remove all the options.
		delete_option( 'wcpay_multi_currency_exchange_rate_usd' );
		delete_option( 'wcpay_multi_currency_manual_rate_usd' );
		delete_option( 'wcpay_multi_currency_price_rounding_usd' );
		delete_option( 'wcpay_multi_currency_price_charm_usd' );

		// Arrange: Create the new REST request.
		$request = new WP_REST_Request( 'POST', self::ROUTE . '/currencies/USD' );
		$request->set_body_params(
			[
				'currency_code'      => 'USD',
				'exchange_rate_type' => 'manual',
				'manual_rate'        => 2,
				'price_rounding'     => 1,
				'price_charm'        => 0,
			]
		);

		// Act: Update the single currency settings.
		$response = $this->controller->update_single_currency_settings( $request );

		// Assert: Confirm the response is what we expected.
		$this->assertEquals( $expected, $response );
	}

	public function test_get_settings_gets_expected_response() {
		// Arrange: Create expected response.
		$expected = rest_ensure_response( WC_Payments_Multi_Currency()->get_settings() );

		// Act: Get the settings.
		$response = $this->controller->get_settings();

		// Assert: Confirm the response is what we expected.
		$this->assertEquals( $expected, $response );
	}

	public function test_update_multi_currency_settings() {
		// Arrange: Add the settings.
		update_option( 'wcpay_multi_currency_enable_auto_currency', 'yes' );
		update_option( 'wcpay_multi_currency_enable_storefront_switcher', 'yes' );

		// Arrange: Create expected response.
		$expected = rest_ensure_response( WC_Payments_Multi_Currency()->get_settings() );

		// Arrange: Now remove all the options.
		delete_option( 'wcpay_multi_currency_enable_auto_currency' );
		delete_option( 'wcpay_multi_currency_enable_storefront_switcher' );

		// Arrange: Create the new REST request.
		$request = new WP_REST_Request( 'POST', self::ROUTE . '/update-settings' );
		$request->set_body_params(
			[
				'wcpay_multi_currency_enable_auto_currency'       => 'yes',
				'wcpay_multi_currency_enable_storefront_switcher' => 'yes',
			]
		);

		// Act: Update the settings.
		$response = $this->controller->update_settings( $request );

		// Assert: Confirm the response is what we expected.
		$this->assertEquals( $expected, $response );
	}
}
