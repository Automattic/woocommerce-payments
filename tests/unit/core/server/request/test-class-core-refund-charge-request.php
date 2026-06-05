<?php
/**
 * Class Refund_Charge_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Core\Exceptions\Server\Request\Invalid_Request_Parameter_Exception;
use WCPay\Core\Server\Request\Refund_Charge;

/**
 * WCPay\Core\Server\Request unit tests.
 */
class Refund_Charge_Test extends WCPAY_UnitTestCase {

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

	public function test_exception_will_throw_if_amount_is_invalid() {
		$request = new Refund_Charge( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_charge( 'ch_xyz' );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->set_amount( 0 );
	}

	public function test_exception_will_throw_if_charge_id_is_not_set() {
		$request = new Refund_Charge( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request->get_params();
	}

	public function test_exception_will_throw_if_charge_id_is_invalid() {
		$this->expectException( Invalid_Request_Parameter_Exception::class );
		$request = new Refund_Charge( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_charge( '1' );
	}

	public function test_py_prefix_will_not_throw_exception() {
		$request = new Refund_Charge( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_charge( 'py_xyz' );
		$this->addToAssertionCount( 1 ); // We're not asserting anything, just not expecting an exception.
	}

	public function test_refund_charge_request_class_is_created() {
		$charge  = 'ch_mock';
		$amount  = 100;
		$request = new Refund_Charge( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_charge( $charge );
		$request->set_amount( $amount );
		$this->assertSame( WC_Payments_API_Client::REFUNDS_API, $request->get_api() );
		$this->assertSame( 'POST', $request->get_method() );
		$params = $request->get_params();
		$this->assertSame( $amount, $params['amount'] );
		$this->assertSame( $charge, $params['charge'] );
	}

	public function test_full_reason_preserves_existing_metadata() {
		$request = new Refund_Charge( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_charge( 'ch_mock' );
		$request->set_source( 'edit_order' );
		$request->set_full_reason( 'Customer changed their mind' );

		$params = $request->get_params();
		$this->assertSame( 'edit_order', $params['metadata']['refund_source'] );
		$this->assertSame( 'Customer changed their mind', $params['metadata']['merchant_refund_reason'] );
	}

	public function test_full_reason_with_stripe_enum_sets_both_reason_and_metadata() {
		$request = new Refund_Charge( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_charge( 'ch_mock' );
		$request->set_full_reason( 'duplicate' );

		$params = $request->get_params();
		$this->assertSame( 'duplicate', $params['reason'] );
		$this->assertSame( 'duplicate', $params['metadata']['merchant_refund_reason'] );
	}

	public function test_full_reason_with_free_text_only_sets_metadata() {
		$request = new Refund_Charge( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_charge( 'ch_mock' );
		$request->set_full_reason( 'Customer changed their mind' );

		$params = $request->get_params();
		$this->assertNull( $params['reason'] );
		$this->assertSame( 'Customer changed their mind', $params['metadata']['merchant_refund_reason'] );
	}

	public function test_full_reason_with_empty_reason_sets_nothing() {
		$request = new Refund_Charge( $this->mock_api_client, $this->mock_wc_payments_http_client );
		$request->set_charge( 'ch_mock' );
		$request->set_full_reason( null );

		$params = $request->get_params();
		$this->assertNull( $params['reason'] );
		$this->assertArrayNotHasKey( 'metadata', $params );
	}
}
