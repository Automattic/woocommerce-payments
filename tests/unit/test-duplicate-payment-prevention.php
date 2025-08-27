<?php
/**
 * Unit tests for duplicate payment prevention functionality.
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Duplicate_Payment_Prevention_Service;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Constants\Intent_Status;

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
	 * @var WC_Order
	 */
	private $mock_order;

	/**
	 * Mock gateway instance.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $mock_gateway;

	/**
	 * Mock order service instance.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $mock_order_service;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create mock instances
		$this->mock_gateway       = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->mock_order_service = $this->createMock( WC_Payments_Order_Service::class );
		$this->mock_order         = $this->createMock( WC_Order::class );

		// Initialize the service
		$this->duplicate_prevention_service = new Duplicate_Payment_Prevention_Service();
		$this->duplicate_prevention_service->init( $this->mock_gateway, $this->mock_order_service );
	}

	/**
	 * Test order-based idempotency key generation.
	 */
	public function test_order_based_idempotency_key_generation() {
		$order_id = 12345;
		$params   = [
			'metadata' => [
				'order_id' => $order_id,
			],
		];

		// Mock the API client
		$api_client = $this->createMock( WC_Payments_API_Client::class );
		
		// Use reflection to access private method
		$reflection = new ReflectionClass( $api_client );
		$method     = $reflection->getMethod( 'get_idempotency_key_for_request' );
		$method->setAccessible( true );

		$idempotency_key = $method->invoke( $api_client, $params );

		// Verify the key is deterministic and order-based
		$this->assertStringStartsWith( 'order_' . $order_id . '_', $idempotency_key );
		
		// Verify same order produces same key
		$idempotency_key_2 = $method->invoke( $api_client, $params );
		$this->assertEquals( $idempotency_key, $idempotency_key_2 );
	}

	/**
	 * Test order-based idempotency key with order number.
	 */
	public function test_order_based_idempotency_key_with_order_number() {
		$order_number = 'WC-12345';
		$params       = [
			'metadata' => [
				'order_number' => $order_number,
			],
		];

		// Mock the API client
		$api_client = $this->createMock( WC_Payments_API_Client::class );
		
		// Use reflection to access private method
		$reflection = new ReflectionClass( $api_client );
		$method     = $reflection->getMethod( 'get_idempotency_key_for_request' );
		$method->setAccessible( true );

		$idempotency_key = $method->invoke( $api_client, $params );

		// Verify the key is deterministic and order-based
		$this->assertStringStartsWith( 'order_' . $order_number . '_', $idempotency_key );
	}

	/**
	 * Test fallback to UUID when no order context is available.
	 */
	public function test_fallback_to_uuid_when_no_order_context() {
		$params = [
			'metadata' => [
				'other_data' => 'value',
			],
		];

		// Mock the API client
		$api_client = $this->createMock( WC_Payments_API_Client::class );
		
		// Use reflection to access private methods
		$reflection = new ReflectionClass( $api_client );
		$method     = $reflection->getMethod( 'get_idempotency_key_for_request' );
		$method->setAccessible( true );

		$idempotency_key = $method->invoke( $api_client, $params );

		// Verify it falls back to UUID format
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $idempotency_key );
	}

	/**
	 * Test order locking functionality.
	 */
	public function test_order_locking_functionality() {
		$order_id = 12345;
		$this->mock_order->method( 'get_id' )->willReturn( $order_id );

		// Test successful locking
		$result = $this->duplicate_prevention_service->lock_order_for_payment_processing( $this->mock_order );
		$this->assertTrue( $result );

		// Test that subsequent lock attempts fail
		$result = $this->duplicate_prevention_service->lock_order_for_payment_processing( $this->mock_order );
		$this->assertFalse( $result );

		// Test unlocking
		$this->duplicate_prevention_service->unlock_order_for_payment_processing( $this->mock_order );

		// Test that locking works again after unlock
		$result = $this->duplicate_prevention_service->lock_order_for_payment_processing( $this->mock_order );
		$this->assertTrue( $result );
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
		$intent_id = 'seti_test_intent_123'; // Setup intent, not payment intent

		$this->mock_order->method( 'get_id' )->willReturn( $order_id );
		$this->mock_order->method( 'get_meta' )->with( '_intent_id', true )->willReturn( $intent_id );

		$result = $this->duplicate_prevention_service->check_for_existing_successful_payment( $this->mock_order );
		$this->assertFalse( $result );
	}

	/**
	 * Test transient cleanup on unlock.
	 */
	public function test_transient_cleanup_on_unlock() {
		$order_id = 12345;
		$this->mock_order->method( 'get_id' )->willReturn( $order_id );

		// Lock the order
		$this->duplicate_prevention_service->lock_order_for_payment_processing( $this->mock_order );

		// Verify transient exists
		$transient_name = 'wcpay_processing_order_' . $order_id;
		$this->assertNotFalse( get_transient( $transient_name ) );

		// Unlock the order
		$this->duplicate_prevention_service->unlock_order_for_payment_processing( $this->mock_order );

		// Verify transient is cleaned up
		$this->assertFalse( get_transient( $transient_name ) );
	}

	/**
	 * Test lock expiration.
	 */
	public function test_lock_expiration() {
		$order_id = 12345;
		$this->mock_order->method( 'get_id' )->willReturn( $order_id );

		// Lock the order
		$this->duplicate_prevention_service->lock_order_for_payment_processing( $this->mock_order );

		// Verify lock exists
		$transient_name = 'wcpay_processing_order_' . $order_id;
		$this->assertNotFalse( get_transient( $transient_name ) );

		// Manually expire the transient (simulate time passing)
		delete_transient( $transient_name );

		// Verify we can lock again after expiration
		$result = $this->duplicate_prevention_service->lock_order_for_payment_processing( $this->mock_order );
		$this->assertTrue( $result );
	}
}
