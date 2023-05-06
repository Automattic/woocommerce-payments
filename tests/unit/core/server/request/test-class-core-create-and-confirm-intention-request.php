<?php
/**
 * Class Create_And_Confirm_Intention_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Immutable_Parameter_Exception;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Create_And_Confirm_Intention;
use WCPay\Core\Server\Request\WooPay_Create_And_Confirm_Intention;

/**
 * WCPay\Core\Server\Create_And_Confirm_Intention_Test unit tests.
 */
class Create_And_Confirm_Intention_Test extends WCPAY_UnitTestCase {

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;
	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_Http_Interface|MockObject
	 */
	private $mock_wc_payments_http_client;


	/**
	 * Set up the unit tests objects.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client              = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_wc_payments_http_client = $this->createMock( WC_Payments_Http_Interface::class );
	}

	public function test_exception_will_throw_if_amount_is_negative_number() {
		$request = new Create_And_Confirm_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_amount( -1 );
	}
	public function test_exception_will_throw_if_amount_is_not_set() {
		$request = new Create_And_Confirm_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_payment_method( 'pm_1' );
		$request->set_customer( 'cus_1' );
		$request->set_metadata( [ 'order_number' => 1 ] );
		$request->set_currency_code( 'usd' );
		$request->get_params();
	}
	public function test_exception_will_throw_if_currency_is_not_set() {
		$request = new Create_And_Confirm_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_amount( 1 );
		$request->set_customer( 'cus_1' );
		$request->set_metadata( [ 'order_number' => 1 ] );
		$request->set_payment_method( 'pm_1' );
		$request->get_params();
	}

	public function test_exception_will_throw_if_payment_method_is_not_set() {
		$request = new Create_And_Confirm_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_amount( 1 );
		$request->set_customer( 'cus_1' );
		$request->set_metadata( [ 'order_number' => 1 ] );
		$request->set_currency_code( 'usd' );
		$request->get_params();
	}

	public function test_exception_will_throw_if_payment_method_is_invalid() {
		$request = new Create_And_Confirm_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_payment_method( '1' );
	}

	public function test_exception_will_throw_if_customer_is_not_set() {
		$request = new Create_And_Confirm_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_amount( 1 );
		$request->set_payment_method( 'pm_1' );
		$request->set_metadata( [ 'order_number' => 1 ] );
		$request->set_currency_code( 'usd' );
		$request->get_params();
	}
	public function test_exception_will_throw_if_customer_is_invalid() {
		$request = new Create_And_Confirm_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_customer( '1' );
	}

	public function test_exception_will_throw_if_amount_parameter_is_changed_when_filter_is_applied() {
		$request = new Create_And_Confirm_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Immutable_Parameter_Exception::class );
		$request->set_amount( 2 );
		$request->set_customer( 'cus_1' );
		$request->set_metadata( [ 'order_number' => 1 ] );
		$request->set_payment_method( 'pm_1' );
		$request->set_currency_code( 'usd' );
		add_filter(
			'cac_test_exception_will_throw_if_immutable_parameter_is_changed_when_filter_is_applied',
			function() {
				$new_class = new class( $this->mock_api_client, $this->mock_wc_payments_http_client) extends Create_And_Confirm_Intention {

				};
				$new_class->set_amount( 3 );
				$new_class->set_payment_method( 'pm_2' );
				$new_class->set_customer( 'cus_2' );
				$new_class->set_metadata( [ 'order_number' => 2 ] );
				$new_class->set_currency_code( 'eur' );

				return $new_class;
			}
		);
		$request->apply_filters( 'cac_test_exception_will_throw_if_immutable_parameter_is_changed_when_filter_is_applied' );
	}

	public function test_create_intent_request_will_be_created() {
		$amount     = 1;
		$currency   = 'usd';
		$pm         = 'pm_1';
		$cs         = 'cus_1';
		$cvc        = 'cvc';
		$return_url = 'localhost/order-received/';

		$request = new Create_And_Confirm_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_amount( $amount );
		$request->set_currency_code( $currency );
		$request->set_payment_method( $pm );
		$request->set_customer( $cs );
		$request->set_capture_method( true );
		$request->setup_future_usage();
		$request->set_metadata( [ 'order_number' => 1 ] );
		$request->set_level3( [ 'level3' => 'level3' ] );
		$request->set_off_session();
		$request->set_payment_methods( [ 'pm' => '1' ] );
		$request->set_cvc_confirmation( $cvc );
		$request->set_return_url( $return_url );
		$this->assertInstanceOf( Create_And_Confirm_Intention::class, $request );
		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $amount, $params['amount'] );
		$this->assertSame( $currency, $params['currency'] );
		$this->assertSame( $pm, $params['payment_method'] );
		$this->assertSame( $cs, $params['customer'] );
		$this->assertSame( 'manual', $params['capture_method'] );
		$this->assertSame( 'off_session', $params['setup_future_usage'] );
		$this->assertArrayHasKey( 'description', $params );
		$this->assertArrayHasKey( 'metadata', $params );
		$this->assertSame( 1, $params['metadata']['order_number'] );
		$this->assertArrayHasKey( 'level3', $params );
		$this->assertSame( 'true', $params['off_session'] );
		$this->assertArrayHasKey( 'payment_method_types', $params );
		$this->assertSame( $cvc, $params['cvc_confirmation'] );
		$this->assertSame( $return_url, $params['return_url'] );
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::INTENTIONS_API, $request->get_api() );
	}

	public function test_woopay_create_intent_request_will_be_created() {
		$amount     = 1;
		$currency   = 'usd';
		$pm         = 'pm_1';
		$cs         = 'cus_1';
		$cvc        = 'cvc';
		$return_url = 'localhost/order-received/';
		$request    = new WooPay_Create_And_Confirm_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_amount( 1 );
		$request->set_currency_code( 'usd' );
		$request->set_payment_method( 'pm_1' );
		$request->set_customer( 'cus_1' );
		$request->set_capture_method( true );
		$request->setup_future_usage();
		$request->set_metadata( [ 'order_number' => 1 ] );
		$request->set_level3( [ 'level3' => 'level3' ] );
		$request->set_off_session();
		$request->set_payment_methods( [ 'pm' => '1' ] );
		$request->set_cvc_confirmation( 'cvc' );
		$request->set_return_url( $return_url );
		$request->set_is_platform_payment_method();
		$request->set_has_woopay_subscription();
		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $amount, $params['amount'] );
		$this->assertSame( $currency, $params['currency'] );
		$this->assertSame( $pm, $params['payment_method'] );
		$this->assertSame( $cs, $params['customer'] );
		$this->assertSame( 'manual', $params['capture_method'] );
		$this->assertSame( 'off_session', $params['setup_future_usage'] );
		$this->assertArrayHasKey( 'description', $params );
		$this->assertArrayHasKey( 'metadata', $params );
		$this->assertSame( 1, $params['metadata']['order_number'] );
		$this->assertArrayHasKey( 'level3', $params );
		$this->assertSame( 'true', $params['off_session'] );
		$this->assertSame( 'true', $params['is_platform_payment_method'] );
		$this->assertSame( 'true', $params['woopay_has_subscription'] );
		$this->assertArrayHasKey( 'payment_method_types', $params );
		$this->assertSame( $cvc, $params['cvc_confirmation'] );
		$this->assertSame( $return_url, $params['return_url'] );
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::INTENTIONS_API, $request->get_api() );
	}
}
