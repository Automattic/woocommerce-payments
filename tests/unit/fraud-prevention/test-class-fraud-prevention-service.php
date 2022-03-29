<?php
/**
 * Class Fraud_Prevention_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Fraud_Prevention\Fraud_Prevention_Service;

/**
 * Fraud_Prevention_Service_Test unit tests.
 */
class Fraud_Prevention_Service_Test extends WP_UnitTestCase {

	/**
	 * WC_Session mock object.
	 *
	 * @var PHPUnit_Framework_MockObject_MockObject|WC_Session
	 */
	private $session_mock;

	/**
	 * WC_Payment_Gateway_WCPay mock object.
	 *
	 * @var PHPUnit_Framework_MockObject_MockObject|WC_Payment_Gateway_WCPay
	 */
	private $wcpay_gateway_mock;

	/**
	 * The service under test.
	 *
	 * @var Fraud_Prevention_Service
	 */
	private $fraud_prevention_service;

	public function set_up() {
		parent::set_up();

		$this->session_mock = $this->getMockBuilder( 'WC_Session' )
			->getMock();

		$this->wcpay_gateway_mock = $this->createMock( WC_Payment_Gateway_WCPay::class );

		Fraud_Prevention_Service::set_instance( null );
		$this->fraud_prevention_service = Fraud_Prevention_Service::get_instance( $this->session_mock, $this->wcpay_gateway_mock );
	}

	public function provide_enabled_options() {
		return [
			[ 'yes', true ],
			[ 'no', false ],
		];
	}

	/**
	 * @dataProvider provide_enabled_options
	 */
	public function test_it_checks_if_enabled( $option_value, $return_value ) {
		$this->wcpay_gateway_mock
			->expects( $this->once() )
			->method( 'get_option' )
			->with( 'is_fraud_prevention_enabled' )
			->willReturn( $option_value );

		$is_enabled = $this->fraud_prevention_service->is_enabled();

		$this->assertSame( $return_value, $is_enabled );
	}

	public function test_it_gets_token_from_session() {
		$token_stub = 'test-token';
		$this->session_mock
			->expects( $this->once() )
			->method( 'get' )
			->with( Fraud_Prevention_Service::TOKEN_NAME )
			->willReturn( $token_stub );

		$token_value = $this->fraud_prevention_service->get_token();

		$this->assertSame( $token_stub, $token_value );
	}

	public function test_it_generates_token_on_first_page_load() {
		$this->session_mock
			->expects( $this->once() )
			->method( 'get' )
			->with( Fraud_Prevention_Service::TOKEN_NAME )
			->willReturn( null );

		$this->session_mock
			->expects( $this->once() )
			->method( 'set' )
			->with( Fraud_Prevention_Service::TOKEN_NAME, $this->isType( 'string' ) );

		$token_value = $this->fraud_prevention_service->get_token();

		$this->assertIsString( $token_value );
	}

	public function test_it_can_regenerate_token() {
		$this->session_mock
			->expects( $this->once() )
			->method( 'set' )
			->with( Fraud_Prevention_Service::TOKEN_NAME, $this->isType( 'string' ) );

		$token_value = $this->fraud_prevention_service->regenerate_token();

		$this->assertIsString( $token_value );
	}

	public function test_it_can_verify_token_is_not_null() {
		$is_valid = $this->fraud_prevention_service->verify_token( null );

		$this->assertFalse( $is_valid );
	}

	public function test_it_can_verify_token_is_incorrect() {
		$this->session_mock
			->expects( $this->once() )
			->method( 'get' )
			->with( Fraud_Prevention_Service::TOKEN_NAME )
			->willReturn( 'actual-token-value' );

		$is_valid = $this->fraud_prevention_service->verify_token( 'incorrect-token-value' );

		$this->assertFalse( $is_valid );
	}

	public function test_it_can_verify_token() {
		$this->session_mock
			->expects( $this->once() )
			->method( 'get' )
			->with( Fraud_Prevention_Service::TOKEN_NAME )
			->willReturn( 'actual-token-value' );

		$is_valid = $this->fraud_prevention_service->verify_token( 'actual-token-value' );

		$this->assertTrue( $is_valid );
	}

}
