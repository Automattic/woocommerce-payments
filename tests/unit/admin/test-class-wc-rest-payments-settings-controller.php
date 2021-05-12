<?php
/**
 * Class WC_REST_Payments_Settings_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\RestApi;
use PHPUnit\Framework\MockObject\MockObject;

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
	 * @var MockObject|WC_Payment_Gateways
	 */
	private $payment_gateways_mock;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		add_action( 'rest_api_init', [ $this, 'deregister_wc_blocks_rest_api' ], 5 );

		// Set the user so that we can pass the authentication.
		wp_set_current_user( 1 );

		/** @var WC_Payments_API_Client|MockObject $mock_api_client */
		$mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
			->disableOriginalConstructor()
			->getMock();

		$account                  = new WC_Payments_Account( $mock_api_client );
		$customer_service         = new WC_Payments_Customer_Service( $mock_api_client, $account );
		$token_service            = new WC_Payments_Token_Service( $mock_api_client, $customer_service );
		$action_scheduler_service = new WC_Payments_Action_Scheduler_Service( $mock_api_client );

		$this->gateway               = new WC_Payment_Gateway_WCPay( $mock_api_client, $account, $customer_service, $token_service, $action_scheduler_service );
		$this->payment_gateways_mock = $this->getMockBuilder( WC_Payment_Gateways::class )
			->disableOriginalConstructor()
			->getMock();
		$this->controller            = new WC_REST_Payments_Settings_Controller( $mock_api_client, $this->gateway, $this->payment_gateways_mock );
	}

	public function test_get_settings_request_returns_status_code_200() {
		$request = new WP_REST_Request( 'GET', self::SETTINGS_ROUTE );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	public function test_get_settings_returns_enabled_payment_method_ids() {
		$enabled_gateway_mock_one = $this->create_wcpay_gateway_mock( 'enabled 1' );
		$enabled_gateway_mock_one->expects( $this->once() )->method( 'is_enabled' )->willReturn( true );

		$enabled_gateway_mock_two = $this->create_wcpay_gateway_mock( 'enabled 2' );
		$enabled_gateway_mock_two->expects( $this->once() )->method( 'is_enabled' )->willReturn( true );

		$disabled_gateway_mock = $this->create_wcpay_gateway_mock( 'disabled' );
		$disabled_gateway_mock->expects( $this->once() )->method( 'is_enabled' )->willReturn( false );

		$this->payment_gateways_mock
			->expects( $this->once() )
			->method( 'payment_gateways' )
			->willReturn( [ $enabled_gateway_mock_one, $enabled_gateway_mock_two, $disabled_gateway_mock ] );

		$response           = $this->controller->get_settings();
		$enabled_method_ids = $response->get_data()['enabled_payment_method_ids'];

		$this->assertEquals(
			[ 'enabled 1', 'enabled 2' ],
			$enabled_method_ids
		);
	}

	public function test_enabled_methods_include_only_subclasses_of_wcpay() {
		$wcpay_subclass_gateway_mock = $this->create_wcpay_gateway_mock( 'foo' );
		$wcpay_subclass_gateway_mock->method( 'is_enabled' )->willReturn( true );

		$non_wcpay_subclass_gateway_mock = $this->create_non_wcpay_gateway_mock( 'bar' );

		$this->set_available_gateways( [ $wcpay_subclass_gateway_mock, $non_wcpay_subclass_gateway_mock ] );

		$response           = $this->controller->get_settings();
		$enabled_method_ids = $response->get_data()['enabled_payment_method_ids'];

		$this->assertEquals(
			[ 'foo' ],
			$enabled_method_ids
		);
	}

	public function test_get_settings_returns_if_wcpay_is_enabled() {
		$this->set_available_gateways( [] );

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
		$request->set_param( 'enabled_payment_method_ids', [ 'woocommerce_payments' ] );

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
		$foo_gateway = $this->create_wcpay_gateway_mock( 'foo' );
		$bar_gateway = $this->create_wcpay_gateway_mock( 'bar' );

		$this->set_available_gateways( [ $foo_gateway, $bar_gateway ] );

		$request = new WP_REST_Request();
		$request->set_param( 'enabled_payment_method_ids', [ $bar_gateway->id ] );

		$foo_gateway->expects( $this->once() )->method( 'disable' );
		$bar_gateway->expects( $this->once() )->method( 'enable' );

		$this->controller->update_settings( $request );
	}

	public function test_update_settings_saves_enabled_payment_method_order() {
		$this->gateway->update_option( 'payment_method_order', [] );
		$this->assertEmpty( $this->gateway->get_option( 'payment_method_order' ) );

		$foo_gateway = $this->create_wcpay_gateway_mock( 'foo' );
		$bar_gateway = $this->create_wcpay_gateway_mock( 'bar' );
		$this->set_available_gateways( [ $foo_gateway, $bar_gateway ] );

		$request = new WP_REST_Request();
		$request->set_param( 'enabled_payment_method_ids', [ $foo_gateway->id, $bar_gateway->id ] );

		$this->controller->update_settings( $request );
		$this->assertEquals( [ 'foo', 'bar' ], $this->gateway->get_option( 'payment_method_order' ) );
	}

	public function test_update_settings_validation_fails_if_invalid_gateway_id_supplied() {
		$foo_gateway = $this->create_wcpay_gateway_mock( 'foo' );

		$this->set_available_gateways( [ $foo_gateway ] );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'enabled_payment_method_ids', [ 'bar' ] );

		$response = rest_do_request( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	public function test_update_settings_validation_fails_if_non_wcpay_gateway_id_supplied() {
		$foo_gateway = $this->create_wcpay_gateway_mock( 'foo' );
		$bar_gateway = $this->create_non_wcpay_gateway_mock( 'bar' );

		$this->set_available_gateways( [ $foo_gateway, $bar_gateway ] );

		$request = new WP_REST_Request( 'POST', self::SETTINGS_ROUTE );
		$request->set_param( 'enabled_payment_method_ids', [ 'bar' ] );

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

	/**
	 * @return MockObject|WC_Payment_Gateway_WCPay
	 */
	private function create_wcpay_gateway_mock( string $id ) {
		$gateway_mock     = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();
		$gateway_mock->id = $id;

		return $gateway_mock;
	}

	/**
	 * @return MockObject|WC_Payment_Gateway
	 */
	private function create_non_wcpay_gateway_mock( string $id ) {
		$gateway_mock     = $this->getMockBuilder( WC_Payment_Gateway::class )
			->disableOriginalConstructor()
			->getMock();
		$gateway_mock->id = $id;

		return $gateway_mock;
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
	 * @param WC_Payment_Gateway[] $gateways Available gateways.
	 */
	private function set_available_gateways( array $gateways ) {
		$this->payment_gateways_mock
			->method( 'payment_gateways' )
			->willReturn( $gateways );
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
