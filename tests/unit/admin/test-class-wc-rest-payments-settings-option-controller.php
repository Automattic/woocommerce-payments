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
			'valid option: wcpay_exit_survey_last_shown' => [
				'wcpay_exit_survey_last_shown',
				true,
			],
			'invalid option: invalid_option'             => [
				'invalid_option',
				false,
			],
			'invalid option: wcpay_invalid_option'       => [
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

	/**
	 * Data provider for valid option values.
	 *
	 * @return array<string, array>
	 */
	public function provider_valid_values(): array {
		return [
			'bool option with true'          => [ 'wcpay_multi_currency_setup_completed', true ],
			'bool option with false'         => [ 'wcpay_multi_currency_setup_completed', false ],
			'array option with empty array'  => [ 'woocommerce_dismissed_todo_tasks', [] ],
			'array option with array'        => [ 'woocommerce_dismissed_todo_tasks', [ 'key' => 'value' ] ],
			'array option with nested array' => [ 'woocommerce_dismissed_todo_tasks', [ 'key' => [ 'nested' => 'value' ] ] ],
			'string option with string'      => [ 'wcpay_exit_survey_last_shown', '2026-01-09T09:23:30.444Z' ],
		];
	}

	/**
	 * @dataProvider provider_valid_values
	 */
	public function test_validate_value_with_valid_values( string $option_name, $value ) {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'option_name', $option_name );

		$result = $this->controller->validate_value( $value, $request );
		$this->assertTrue( $result );
	}

	/**
	 * Data provider for invalid option values.
	 *
	 * @return array<string, array>
	 */
	public function provider_invalid_values(): array {
		return [
			'bool option with string'  => [ 'wcpay_multi_currency_setup_completed', 'string' ],
			'bool option with array'   => [ 'wcpay_multi_currency_setup_completed', [] ],
			'bool option with int'     => [ 'wcpay_multi_currency_setup_completed', 123 ],
			'array option with bool'   => [ 'woocommerce_dismissed_todo_tasks', true ],
			'array option with string' => [ 'woocommerce_dismissed_todo_tasks', 'string' ],
			'string option with bool'  => [ 'wcpay_exit_survey_last_shown', true ],
			'string option with array' => [ 'wcpay_exit_survey_last_shown', [] ],
			'string option with int'   => [ 'wcpay_exit_survey_last_shown', 123 ],
		];
	}

	/**
	 * @dataProvider provider_invalid_values
	 */
	public function test_validate_value_with_invalid_values( string $option_name, $value ) {
		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'option_name', $option_name );

		$result = $this->controller->validate_value( $value, $request );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'rest_invalid_param', $result->get_error_code() );
		$this->assertEquals( 400, $result->get_error_data()['status'] );
	}
}
