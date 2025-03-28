<?php
/**
 * Class WC_REST_Payments_Settings_Option_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_REST_Payments_Settings_Option_Controller unit tests.
 */
class WC_REST_Payments_Settings_Option_Controller_Test extends WCPAY_UnitTestCase {

	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Settings_Option_Controller
	 */
	private $controller;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		$this->controller = new WC_REST_Payments_Settings_Option_Controller( $this->createMock( WC_Payments_API_Client::class ) );
	}

	public function test_update_option_success() {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'option_name', 'wcpay_multi_currency_setup_completed' );
		$request->set_param( 'value', true );

		$response = $this->controller->update_option( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	public function provider_option_names(): array {
		return [
			'valid option: wcpay_multi_currency_setup_completed' => [
				'wcpay_multi_currency_setup_completed',
				true,
			],
			'valid option: woocommerce_dismissed_todo_tasks' => [
				'woocommerce_dismissed_todo_tasks',
				true,
			],
			'valid option: wcpay_fraud_protection_welcome_tour_dismissed' => [
				'wcpay_fraud_protection_welcome_tour_dismissed',
				true,
			],
			'invalid option: invalid_option'       => [
				'invalid_option',
				false,
			],
			'invalid option: wcpay_invalid_option' => [
				'wcpay_invalid_option',
				false,
			],
		];
	}

	/**
	 * @dataProvider provider_option_names
	 */
	public function test_validate_option_name( string $option, bool $expected_result ) {
		$this->assertSame( $expected_result, $this->controller->validate_option_name( $option ) );
	}

	public function test_validate_value_with_valid_values() {
		$valid_values = [
			true,
			false,
			[],
			[ 'key' => 'value' ],
			[ 'key' => [ 'nested_key' => 'nested_value' ] ],
		];

		foreach ( $valid_values as $value ) {
			$result = $this->controller->validate_value( $value );
			$this->assertTrue( $result );
		}
	}

	public function test_validate_value_with_invalid_values() {
		$invalid_values = [
			'string',
			123,
			null,
			(object) [],
		];

		foreach ( $invalid_values as $value ) {
			$result = $this->controller->validate_value( $value );
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertEquals( 'rest_invalid_param', $result->get_error_code() );
			$this->assertEquals( 400, $result->get_error_data()['status'] );
		}
	}
}
