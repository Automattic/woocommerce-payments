<?php
/**
 * Unit tests for duplicate payment prevention functionality.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Constants\Intent_Status;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * WC_Payments_Duplicate_Payment_Prevention_Test class.
 */
class WC_Payments_Duplicate_Payment_Prevention_Test extends WCPAY_UnitTestCase {

	/**
	 * Duplicate payment prevention service instance.
	 *
	 * @var Duplicate_Payment_Prevention_Service
	 */
	private $duplicate_prevention_service;

	/**
	 * Mock order instance.
	 *
	 * @var WC_Order|MockObject
	 */
	private $mock_order;

	/**
	 * Mock gateway instance.
	 *
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_gateway;

	/**
	 * Mock order service instance.
	 *
	 * @var WC_Payments_Order_Service|MockObject
	 */
	private $mock_order_service;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create mock instances.
		$this->mock_gateway       = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->mock_order_service = $this->createMock( WC_Payments_Order_Service::class );
		$this->mock_order         = $this->createMock( WC_Order::class );

		// Initialize the service.
		$this->duplicate_prevention_service = new Duplicate_Payment_Prevention_Service();
		$this->duplicate_prevention_service->init( $this->mock_gateway, $this->mock_order_service );
	}

	/**
	 * Test that the duplicate prevention service exists and can be instantiated.
	 */
	public function test_duplicate_prevention_service_exists() {
		$this->assertInstanceOf( Duplicate_Payment_Prevention_Service::class, $this->duplicate_prevention_service );
	}

	/**
	 * Test existing successful payment detection with no intent ID.
	 */
	public function test_existing_successful_payment_detection_no_intent() {
		$order_id = 12345;

		$this->mock_order->method( 'get_id' )->willReturn( $order_id );
		$this->mock_order->method( 'get_meta' )->with( '_intent_id', true )->willReturn( '' );

		$result = $this->duplicate_prevention_service->check_for_existing_successful_payment( $this->mock_order );
		$this->assertFalse( $result );
	}

	/**
	 * Test existing successful payment detection with setup intent.
	 */
	public function test_existing_successful_payment_detection_setup_intent() {
		$order_id  = 12345;
		$intent_id = 'seti_test_intent_123'; // Setup intent, not payment intent.

		$this->mock_order->method( 'get_id' )->willReturn( $order_id );
		$this->mock_order->method( 'get_meta' )->with( '_intent_id', true )->willReturn( $intent_id );

		$result = $this->duplicate_prevention_service->check_for_existing_successful_payment( $this->mock_order );
		$this->assertFalse( $result );
	}
}
