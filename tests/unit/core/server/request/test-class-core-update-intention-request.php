<?php
/**
 * Class Update_Intention_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Update_Intention;

/**
 * WCPay\Core\Server\Update_Intention_Test unit tests.
 */
class Update_Intention_Test extends WCPAY_UnitTestCase {

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
		$request = new Update_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client, 'pi_mock' );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_amount( -1 );
	}
	public function test_exception_will_throw_if_customer_is_invalid() {
		$request = new Update_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client, 'pi_mock' );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_customer( '1' );
	}

	public function test_exception_will_throw_if_intent_is_invalid() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request = new Update_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client, '1' );
	}

	public function test_update_intent_request_will_be_created() {
		$amount       = 1;
		$currency     = 'usd';
		$cs           = 'cus_1';
		$country      = 'usa';
		$payment_type = 'card';
		$intent_id    = 'pi_1';

		$request = new Update_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client, $intent_id );
		$request->set_amount( $amount );
		$request->set_currency_code( $currency );
		$request->set_customer( $cs );
		$request->setup_future_usage();
		$request->set_metadata( [ 'order_number' => 1 ] );
		$request->set_level3( [ 'level3' => 'level3' ] );
		$request->set_payment_method_types( [ $payment_type ] );
		$request->set_payment_country( $country );
		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $amount, $params['amount'] );
		$this->assertSame( '', $params['receipt_email'] );
		$this->assertSame( $currency, $params['currency'] );
		$this->assertSame( $cs, $params['customer'] );
		$this->assertSame( 'off_session', $params['setup_future_usage'] );
		$this->assertArrayHasKey( 'description', $params );
		$this->assertArrayHasKey( 'metadata', $params );
		$this->assertSame( 1, $params['metadata']['order_number'] );
		$this->assertSame( $payment_type, $params['payment_method_types'][0] );
		$this->assertArrayHasKey( 'level3', $params );
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::INTENTIONS_API . '/' . $intent_id, $request->get_api() );
	}
}
