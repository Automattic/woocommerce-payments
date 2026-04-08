<?php
/**
 * Class WC_Payments_Payment_Method_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\API_Exception;
use WCPay\Logger;

/**
 * WC_Payments_Payment_Method_Service unit tests.
 */
class WC_Payments_Payment_Method_Service_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var WC_Payments_Payment_Method_Service
	 */
	private $payment_method_service;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock WC_Payments_Order_Service.
	 *
	 * @var WC_Payments_Order_Service|MockObject
	 */
	private $mock_order_service;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_api_client    = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_order_service = $this->createMock( WC_Payments_Order_Service::class );

		$this->payment_method_service = new WC_Payments_Payment_Method_Service(
			$this->mock_api_client,
			$this->mock_order_service
		);
	}

	/**
	 * Test get_card_info returns original card_info when order payment method is not WCPay.
	 */
	public function test_get_card_info_returns_original_when_not_wcpay_order() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_payment_method' )->willReturn( 'some_other_gateway' );

		$original_card_info = [
			'brand' => 'visa',
			'last4' => '1234',
		];

		$result = $this->payment_method_service->get_card_info( $original_card_info, $order );

		$this->assertEquals( $original_card_info, $result );
	}

	/**
	 * Test get_card_info returns original card_info when payment method details are not available.
	 */
	public function test_get_card_info_returns_original_when_no_payment_method_details() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_payment_method' )->willReturn( WC_Payment_Gateway_WCPay::GATEWAY_ID );

		$this->mock_order_service
			->method( 'get_payment_method_details' )
			->willReturn( null );

		$order->method( 'get_meta' )
			->with( '_payment_method_id' )
			->willReturn( null );

		$original_card_info = [
			'brand' => 'visa',
			'last4' => '1234',
		];

		$result = $this->payment_method_service->get_card_info( $original_card_info, $order );

		$this->assertEquals( $original_card_info, $result );
	}

	/**
	 * Test get_card_info handles API exception gracefully.
	 */
	public function test_get_card_info_handles_api_exception() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_payment_method' )->willReturn( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$order->method( 'get_id' )->willReturn( 123 );

		$this->mock_order_service
			->method( 'get_payment_method_details' )
			->willReturn( null );

		$order->method( 'get_meta' )
			->with( '_payment_method_id' )
			->willReturn( 'pm_test_123' );

		$this->mock_api_client
			->method( 'get_payment_method' )
			->willThrowException( new API_Exception( 'API Error', 'api_error', 500 ) );

		$original_card_info = [
			'brand' => 'visa',
			'last4' => '1234',
		];

		$result = $this->payment_method_service->get_card_info( $original_card_info, $order );

		$this->assertEquals( $original_card_info, $result );
	}

	/**
	 * Test get_card_info processes card payment method correctly.
	 */
	public function test_get_card_info_processes_card_payment_method() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_payment_method' )->willReturn( WC_Payment_Gateway_WCPay::GATEWAY_ID );

		$payment_method_details = [
			'type' => 'card',
			'card' => [
				'brand' => 'visa',
				'last4' => '4242',
			],
		];

		$this->mock_order_service
			->method( 'get_payment_method_details' )
			->willReturn( $payment_method_details );

		$original_card_info = [];

		$result = $this->payment_method_service->get_card_info( $original_card_info, $order );

		$expected = [
			'brand' => 'visa',
			'last4' => '4242',
		];

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test get_card_info processes card_present payment method correctly.
	 *
	 * Also tests cache.
	 */
	public function test_get_card_info_processes_card_present_payment_method() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_payment_method' )->willReturn( WC_Payment_Gateway_WCPay::GATEWAY_ID );

		$payment_method_details = [
			'type'         => 'card_present',
			'card_present' => [
				'brand'   => 'visa',
				'last4'   => '4242',
				'receipt' => [
					'account_type'               => 'credit',
					'dedicated_file_name'        => 'A0000000031010',
					'application_preferred_name' => 'VISA',
				],
			],
		];

		$this->mock_order_service
			->method( 'get_payment_method_details' )
			->willReturn( $payment_method_details );

		$original_card_info = [];

		$result = $this->payment_method_service->get_card_info( $original_card_info, $order );

		$expected = [
			'brand'        => 'visa',
			'last4'        => '4242',
			'account_type' => 'credit',
			'aid'          => 'A0000000031010',
			'app_name'     => 'VISA',
		];

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test get_card_info caches payment method details when retrieved from API.
	 */
	public function test_get_card_info_caches_payment_method_details() {
		$order = $this->createMock( WC_Order::class );
		$order->method( 'get_payment_method' )->willReturn( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$order->method( 'get_id' )->willReturn( 123 );

		$this->mock_order_service
			->method( 'get_payment_method_details' )
			->willReturn( null );

		$order->method( 'get_meta' )
			->with( '_payment_method_id' )
			->willReturn( 'pm_test_123' );

		$payment_method_details = [
			'type' => 'card',
			'card' => [
				'brand' => 'visa',
				'last4' => '4242',
			],
		];

		$this->mock_api_client
			->method( 'get_payment_method' )
			->with( 'pm_test_123' )
			->willReturn( $payment_method_details );

		$this->mock_order_service
			->expects( $this->once() )
			->method( 'store_payment_method_details' )
			->with( $order, $payment_method_details );

		$original_card_info = [];

		$this->payment_method_service->get_card_info( $original_card_info, $order );
	}
}
