<?php
/**
 * Class Create_And_Confirm_Setup_Intention_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Immutable_Parameter_Exception;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Create_And_Confirm_Setup_Intention;
use WCPay\Core\Server\Request\WooPay_Create_And_Confirm_Setup_Intention;

/**
 * WCPay\Core\Server\Create_And_Confirm_Setup_Intention_Test unit tests.
 */
class Create_And_Confirm_Setup_Intention_Test extends WCPAY_UnitTestCase {

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

	public function test_exception_will_throw_if_payment_method_is_invalid() {
		$request = new Create_And_Confirm_Setup_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_payment_method( '1' );
	}

	public function test_exception_will_throw_if_customer_is_not_set() {
		$request = new Create_And_Confirm_Setup_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_payment_method( 'pm_1' );
		$request->set_metadata( [ 'order_number' => 1 ] );
		$request->get_params();
	}
	public function test_exception_will_throw_if_customer_is_invalid() {
		$request = new Create_And_Confirm_Setup_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_customer( '1' );
	}


	public function create_intent_request_provider() {
		return [
			[ 'pm_1', 'cus_1' ],
			[ 'src_2', 'cus_3' ],
			[ 'card_1', 'cus_1' ],
		];
	}

	/**
	 *   * @dataProvider create_intent_request_provider
	 */
	public function test_create_intent_request_will_be_created( $payment_method, $customer_id ) {

		$request = new Create_And_Confirm_Setup_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_payment_method( $payment_method );
		$request->set_customer( $customer_id );
		$this->assertInstanceOf( Create_And_Confirm_Setup_Intention::class, $request );
		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $payment_method, $params['payment_method'] );
		$this->assertSame( $customer_id, $params['customer'] );
		$this->assertArrayHasKey( 'metadata', $params );
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::SETUP_INTENTS_API, $request->get_api() );
	}

	public function test_woopay_create_intent_request_will_be_created() {

		$pm      = 'pm_1';
		$cs      = 'cus_1';
		$request = new WooPay_Create_And_Confirm_Setup_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_payment_method( 'pm_1' );
		$request->set_customer( 'cus_1' );
		$request->set_metadata( [ 'order_number' => 1 ] );
		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $pm, $params['payment_method'] );
		$this->assertSame( $cs, $params['customer'] );
		$this->assertArrayHasKey( 'metadata', $params );
		$this->assertSame( 1, $params['metadata']['order_number'] );
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::SETUP_INTENTS_API, $request->get_api() );
	}
}
