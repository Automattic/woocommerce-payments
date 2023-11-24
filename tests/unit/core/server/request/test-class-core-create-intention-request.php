<?php
/**
 * Class Create_Intention_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Immutable_Parameter_Exception;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Create_Intention;
use WCPay\Core\Server\Request\WooPay_Create_Intent;

/**
 * WCPay\Core\Server\Create_Intention_Test unit tests.
 */
class Create_Intention_Test extends WCPAY_UnitTestCase {

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
		$request = new Create_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_amount( -1 );
	}
	public function test_exception_will_throw_if_amount_is_not_set() {
		$request = new Create_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_currency_code( 'usd' );
		$request->get_params();
	}
	public function test_exception_will_throw_if_currency_is_not_set() {
		$request = new Create_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_amount( 1 );
		$request->get_params();
	}

	public function test_exception_will_throw_if_amount_parameter_is_changed_when_filter_is_applied() {
		$request = new Create_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Immutable_Parameter_Exception::class );
		$request->set_amount( 1 );
		$request->set_currency_code( 'usd' );
		add_filter(
			'test_exception_will_throw_if_immutable_parameter_is_changed_when_filter_is_applied',
			function() {
				$new_class = new class( $this->mock_api_client, $this->mock_wc_payments_http_client) extends Create_Intention {

				};
				$new_class->set_amount( 3 );
				$new_class->set_currency_code( 'usd' );
				return $new_class;
			}
		);
		$request->apply_filters( 'test_exception_will_throw_if_immutable_parameter_is_changed_when_filter_is_applied' );
	}

	public function test_create_intent_request_will_be_created() {
		$amount   = 1;
		$currency = 'usd';
		$request  = new Create_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_amount( $amount );
		$request->set_currency_code( $currency );
		$this->assertInstanceOf( Create_Intention::class, $request );
		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertArrayHasKey( 'amount', $params );
		$this->assertSame( $amount, $params['amount'] );
		$this->assertArrayHasKey( 'currency', $params );
		$this->assertSame( $currency, $params['currency'] );
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::INTENTIONS_API, $request->get_api() );
	}

	public function test_woopay_create_intent_request_will_be_created() {
		$amount   = 1;
		$currency = 'usd';
		$request  = new WooPay_Create_Intent( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_amount( $amount );
		$request->set_save_payment_method_to_platform( true );
		$request->set_currency_code( $currency );
		$this->assertInstanceOf( WooPay_Create_Intent::class, $request );
		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertArrayHasKey( 'amount', $params );
		$this->assertSame( $amount, $params['amount'] );
		$this->assertArrayHasKey( 'currency', $params );
		$this->assertSame( 'true', $params['save_payment_method_to_platform'] );
		$this->assertSame( $currency, $params['currency'] );
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::INTENTIONS_API, $request->get_api() );
	}
}
