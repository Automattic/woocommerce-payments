<?php
/**
 * Class Capture_Intention_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Capture_Intention;

/**
 * WCPay\Core\Server\Capture_Intention_Test unit tests.
 */
class Capture_Intention_Test extends WCPAY_UnitTestCase {

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

	public function test_exception_will_throw_if_intent_is_invalid() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		new Capture_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client, '1' );
	}

	public function test_capture_intent_request_will_be_created() {
		$amount    = 1;
		$intent_id = 'pi_1';

		$request = new Capture_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client, $intent_id );
		$request->set_amount_to_capture( $amount );
		$request->set_level3( [ 'level3' => 'level3' ] );
		$params = $request->get_params();

		$this->assertIsArray( $params );
		$this->assertSame( $amount, $params['amount_to_capture'] );
		$this->assertArrayHasKey( 'level3', $params );
		$this->assertSame( 'POST', $request->get_method() );
		$this->assertSame( WC_Payments_API_Client::INTENTIONS_API . '/' . $intent_id . '/capture', $request->get_api() );
	}
	/** Creating and capturing requests opt in without changing their defaults. */
	public function test_payment_operation_reporting_context_is_opt_in() {
		foreach ( [
			new Capture_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client, 'pi_1' ),
			new \WCPay\Core\Server\Request\Create_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client ),
			new \WCPay\Core\Server\Request\Create_And_Confirm_Intention( $this->mock_api_client, $this->mock_wc_payments_http_client ),
		] as $request ) {
			if ( $request instanceof Capture_Intention ) {
				$request->set_amount_to_capture( 3600 );
			} else {
				$request->set_amount( 3600 );
				$request->set_currency_code( 'gbp' );
				$request->set_customer( 'cus_fixture' );
				$request->set_metadata( [ 'order_number' => 1 ] );
			}
			$before = $request->get_params();
			$this->assertArrayNotHasKey( 'include_reporting_context', $before );
			$request->set_include_reporting_context();
			$this->assertSame( array_merge( $before, [ 'include_reporting_context' => 1 ] ), $request->get_params() );
		}
	}
}
