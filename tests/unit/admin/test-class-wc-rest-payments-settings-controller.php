<?php
/**
 * Class WC_REST_Payments_Settings_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\RestApi;

/**
 * WC_REST_Payments_Settings_Controller_Test unit tests.
 */
class WC_REST_Payments_Settings_Controller_Test extends WP_UnitTestCase {

	/**
	 * Tested REST route.
	 */
	const SETTINGS_ROUTE = '/wc/v3/payments/settings';

	/**
	 * The system under test.
	 *
	 * @var WC_REST_Payments_Settings_Controller
	 */
	private $controller;

	/**
	 * Gateway.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $gateway;

	/**
	 * @var WC_Payments_API_Client
	 */
	private $mock_api_client;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		add_action( 'rest_api_init', [ $this, 'deregister_wc_blocks_rest_api' ], 5 );
		remove_filter( 'woocommerce_settings_api_sanitized_fields_woocommerce_payments', [ WC_Payments::get_gateway(), 'sanitize_plugin_settings' ] );

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );
		update_option( '_wcpay_feature_grouped_settings', '1' );
		$this->set_available_gateways( [ 'card' ] );

		$this->mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
			->disableOriginalConstructor()
			->getMock();

		$account                  = new WC_Payments_Account( $this->mock_api_client );
		$customer_service         = new WC_Payments_Customer_Service( $this->mock_api_client, $account );
		$token_service            = new WC_Payments_Token_Service( $this->mock_api_client, $customer_service );
		$action_scheduler_service = new WC_Payments_Action_Scheduler_Service( $this->mock_api_client );

		$this->gateway    = new WC_Payment_Gateway_WCPay( $this->mock_api_client, $account, $customer_service, $token_service, $action_scheduler_service );
		$this->controller = new WC_REST_Payments_Settings_Controller( $this->mock_api_client, $this->gateway );
	}

	public function test_get_settings_request_returns_status_code_200() {
		$request = new WP_REST_Request( 'GET', self::SETTINGS_ROUTE );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_get_settings_returns_enabled_payment_method_ids() {
		$response           = $this->controller->get_settings();
		$enabled_method_ids = $response->get_data()['enabled_payment_method_ids'];

		$this->assertEquals(
			[ 'card' ],
			$enabled_method_ids
		);
	}

	public function test_get_settings_returns_available_payment_method_ids() {
		$this->set_available_gateways( [ 'foo', 'bar' ] );
		$response           = $this->controller->get_settings();
		$enabled_method_ids = $response->get_data()['available_payment_method_ids'];

		$this->assertEquals(
			[ 'foo', 'bar' ],
			$enabled_method_ids
		);
	}

	public function test_get_settings_returns_if_wcpay_is_enabled() {
		$this->gateway->enable();
		$response = $this->controller->get_settings();
		$this->assertTrue( $response->get_data()['is_wcpay_enabled'] );

		$this->gateway->disable();
		$response = $this->controller->get_settings();
		$this->assertFalse( $response->get_data()['is_wcpay_enabled'] );
	}

	public function test_get_settings_fails_if_user_cannot_manage_woocommerce() {
		$this->set_available_gateways( [] );

		$cb = $this->create_can_manage_woocommerce_cap_override( false );
		add_filter( 'user_has_cap', $cb );
		$response = rest_do_request( new WP_REST_Request( 'GET', self::SETTINGS_ROUTE ) );
		$this->assertEquals( 403, $response->get_status() );
		remove_filter( 'user_has_cap', $cb );

		$cb = $this->create_can_manage_woocommerce_cap_override( true );
		add_filter( 'user_has_cap', $cb );
		$response = rest_do_request( new WP_REST_Request( 'GET', self::SETTINGS_ROUTE ) );
		$this->assertEquals( 200, $response->get_status() );
		remove_filter( 'user_has_cap', $cb );
	}

	public function test_update_settings_request_returns_status_code_200() {
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_wcpay_enabled', true );
		$request->set_param( 'enabled_payment_method_ids', [ 'card' ] );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_update_settings_enables_wcpay() {
		$request = new WP_REST_Request();
		$request->set_param( 'is_wcpay_enabled', true );

		$this->controller->update_settings( $request );

		$this->assertTrue( $this->gateway->is_enabled() );
	}

	public function test_update_settings_disables_wcpay() {
		$request = new WP_REST_Request();
		$request->set_param( 'is_wcpay_enabled', false );

		$this->controller->update_settings( $request );

		$this->assertFalse( $this->gateway->is_enabled() );
	}

	public function test_update_settings_does_not_toggle_is_wcpay_enabled_if_not_supplied() {
		$status_before_request = $this->gateway->is_enabled();

		$request = new WP_REST_Request();

		$this->controller->update_settings( $request );

		$this->assertEquals( $status_before_request, $this->gateway->is_enabled() );
	}

	public function test_update_settings_returns_error_on_non_bool_is_wcpay_enabled_value() {
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_wcpay_enabled', 'foo' );

		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_update_settings_saves_enabled_payment_methods() {
		$this->set_available_gateways( [ 'foo', 'bar' ] );

		$request = new WP_REST_Request();
		$request->set_param( 'enabled_payment_method_ids', [ 'bar' ] );

		$this->controller->update_settings( $request );

		$this->assertEquals( [ 'bar' ], $this->gateway->get_option( 'enabled_payment_method_ids' ) );
	}

	public function test_update_settings_validation_fails_if_invalid_gateway_id_supplied() {
		$this->set_available_gateways( [ 'foo', 'bar' ] );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'enabled_payment_method_ids', [ 'foo', 'baz' ] );

		$response = rest_do_request( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_update_settings_fails_if_user_cannot_manage_woocommerce() {
		$cb = $this->create_can_manage_woocommerce_cap_override( false );
		add_filter( 'user_has_cap', $cb );
		$response = rest_do_request( new WP_REST_Request( 'POST', self::SETTINGS_ROUTE ) );
		$this->assertEquals( 403, $response->get_status() );
		remove_filter( 'user_has_cap', $cb );

		$cb = $this->create_can_manage_woocommerce_cap_override( true );
		add_filter( 'user_has_cap', $cb );
		$response = rest_do_request( new WP_REST_Request( 'POST', self::SETTINGS_ROUTE ) );
		$this->assertEquals( 200, $response->get_status() );
		remove_filter( 'user_has_cap', $cb );
	}

	public function test_update_settings_enables_manual_capture() {
		$request = new WP_REST_Request();
		$request->set_param( 'is_manual_capture_enabled', true );

		$this->controller->update_settings( $request );

		$this->assertEquals( 'yes', $this->gateway->get_option( 'manual_capture' ) );
	}

	public function test_update_settings_disables_manual_capture() {
		$request = new WP_REST_Request();
		$request->set_param( 'is_manual_capture_enabled', false );

		$this->controller->update_settings( $request );

		$this->assertEquals( 'no', $this->gateway->get_option( 'manual_capture' ) );
	}

	public function test_update_settings_does_not_toggle_is_manual_capture_enabled_if_not_supplied() {
		$status_before_request = $this->gateway->get_option( 'manual_capture' );

		$request = new WP_REST_Request();

		$this->controller->update_settings( $request );

		$this->assertEquals( $status_before_request, $this->gateway->get_option( 'manual_capture' ) );
	}

	public function test_update_settings_returns_error_on_non_bool_is_manual_capture_enabled_value() {
		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'is_manual_capture_enabled', 'foo' );

		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_update_settings_saves_debug_log() {
		$this->assertEquals( 'no', $this->gateway->get_option( 'enable_logging' ) );

		$request = new WP_REST_Request();
		$request->set_param( 'is_debug_log_enabled', true );

		$this->controller->update_settings( $request );

		$this->assertEquals( 'yes', $this->gateway->get_option( 'enable_logging' ) );
	}

	public function test_update_settings_does_not_save_debug_log_when_dev_mode_enabled() {
		add_filter(
			'wcpay_dev_mode',
			function () {
				return true;
			}
		);
		$this->assertEquals( 'no', $this->gateway->get_option( 'enable_logging' ) );

		$request = new WP_REST_Request();
		$request->set_param( 'is_debug_log_enabled', true );

		$this->controller->update_settings( $request );

		$this->assertEquals( 'no', $this->gateway->get_option( 'enable_logging' ) );
	}

	public function test_update_settings_saves_test_mode() {
		$this->assertEquals( 'no', $this->gateway->get_option( 'test_mode' ) );

		$request = new WP_REST_Request();
		$request->set_param( 'is_test_mode_enabled', true );

		$this->controller->update_settings( $request );

		$this->assertEquals( 'yes', $this->gateway->get_option( 'test_mode' ) );
	}

	public function test_update_settings_does_not_save_test_mode_when_dev_mode_enabled() {
		add_filter(
			'wcpay_dev_mode',
			function () {
				return true;
			}
		);
		$this->assertEquals( 'no', $this->gateway->get_option( 'test_mode' ) );
		$this->assertEquals( true, $this->gateway->is_in_test_mode() );

		$request = new WP_REST_Request();
		$request->set_param( 'is_test_mode_enabled', true );

		$this->controller->update_settings( $request );

		$this->assertEquals( 'no', $this->gateway->get_option( 'test_mode' ) );
		$this->assertEquals( true, $this->gateway->is_in_test_mode() );
	}

	public function test_update_settings_saves_account_statement_descriptor() {
		$new_account_descriptor = 'new account descriptor';

		$this->mock_api_client->expects( $this->once() )
			->method( 'update_account' )
			->with( $this->equalTo( [ 'statement_descriptor' => $new_account_descriptor ] ) );

		$request = new WP_REST_Request();
		$request->set_param( 'account_statement_descriptor', $new_account_descriptor );

		$this->controller->update_settings( $request );
	}

	public function test_update_settings_saves_digital_wallets_button_theme() {
		$this->assertEquals( 'dark', $this->gateway->get_option( 'payment_request_button_theme' ) );

		$request = new WP_REST_Request();
		$request->set_param( 'digital_wallets_button_theme', 'light' );

		$this->controller->update_settings( $request );

		$this->assertEquals( 'light', $this->gateway->get_option( 'payment_request_button_theme' ) );
	}

	public function test_update_settings_saves_digital_wallets_button_size() {
		$this->assertEquals( 'default', $this->gateway->get_option( 'payment_request_button_size' ) );

		$request = new WP_REST_Request();
		$request->set_param( 'digital_wallets_button_size', 'medium' );

		$this->controller->update_settings( $request );

		$this->assertEquals( 'medium', $this->gateway->get_option( 'payment_request_button_size' ) );
	}

	public function test_update_settings_saves_digital_wallets_button_type() {
		$this->assertEquals( 'buy', $this->gateway->get_option( 'payment_request_button_type' ) );

		$request = new WP_REST_Request();
		$request->set_param( 'digital_wallets_button_type', 'book' );

		$this->controller->update_settings( $request );

		$this->assertEquals( 'book', $this->gateway->get_option( 'payment_request_button_type' ) );
	}

	public function test_update_settings_does_not_save_account_statement_descriptor_if_not_supplied() {
		$status_before_request = $this->gateway->get_option( 'account_statement_descriptor' );

		$request = new WP_REST_Request();

		$this->mock_api_client->expects( $this->never() )
			->method( 'update_account' )
			->with( $this->anything() );

		$this->controller->update_settings( $request );
	}
	/**
	 * @param bool $can_manage_woocommerce
	 *
	 * @return Closure
	 */
	private function create_can_manage_woocommerce_cap_override( bool $can_manage_woocommerce ) {
		return function ( $allcaps ) use ( $can_manage_woocommerce ) {
			$allcaps['manage_woocommerce'] = $can_manage_woocommerce;

			return $allcaps;
		};
	}

	/**
	 * @param string[] $gateways Available gateways.
	 */
	private function set_available_gateways( array $gateways ) {
		add_filter(
			'wcpay_upe_available_payment_methods',
			function () use ( $gateways ) {
				return $gateways;
			}
		);
	}

	/**
	 * Deregister WooCommerce Blocks REST routes to prevent _doing_it_wrong() notices
	 * after calls to rest_do_request().
	 */
	public function deregister_wc_blocks_rest_api() {
		try {
			/* For WooCommerce Blocks >= 2.6.0: */
			$wc_blocks_rest_api = Package::container()->get( RestApi::class );
			remove_action( 'rest_api_init', [ $wc_blocks_rest_api, 'register_rest_routes' ] );
		} catch ( Exception $e ) {
			/* For WooCommerce Blocks < 2.6.0: */
			remove_action( 'rest_api_init', [ RestApi::class, 'register_rest_routes' ] );
		}
	}
}
