<?php
/**
 * Integration tests for duplicate payment prevention functionality.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * WC_Payments_Duplicate_Payment_Prevention_Integration_Test class.
 */
class WC_Payments_Duplicate_Payment_Prevention_Integration_Test extends WCPAY_UnitTestCase {

	/**
	 * Test duplicate payment prevention in payment processing flow.
	 */
	public function test_duplicate_payment_prevention_in_payment_flow() {
		// Create a test order
		$order = wc_create_order();
		$order->set_total( 100.00 );
		$order->set_currency( 'USD' );
		$order->save();

		// Mock the gateway
		$gateway = $this->get_mock_gateway();

		// First payment attempt should succeed
		$result1 = $gateway->process_payment( $order->get_id() );
		$this->assertEquals( 'success', $result1['result'] );

		// Second payment attempt should be prevented
		$result2 = $gateway->process_payment( $order->get_id() );
		$this->assertEquals( 'fail', $result2['result'] );
		$this->assertStringContainsString( 'already been paid', $result2['messages'] );
	}

	/**
	 * Test concurrent payment processing prevention.
	 */
	public function test_concurrent_payment_processing_prevention() {
		// Create a test order
		$order = wc_create_order();
		$order->set_total( 100.00 );
		$order->set_currency( 'USD' );
		$order->save();

		// Mock the gateway
		$gateway = $this->get_mock_gateway();

		// Simulate concurrent processing by manually locking the order
		$duplicate_prevention_service = new WCPay\Duplicate_Payment_Prevention_Service();
		$duplicate_prevention_service->lock_order_for_payment_processing( $order );

		// Payment attempt should be prevented due to lock
		$result = $gateway->process_payment( $order->get_id() );
		$this->assertEquals( 'fail', $result['result'] );
		$this->assertStringContainsString( 'currently being processed', $result['messages'] );

		// Clean up
		$duplicate_prevention_service->unlock_order_for_payment_processing( $order );
	}

	/**
	 * Test idempotency key consistency across multiple requests.
	 */
	public function test_idempotency_key_consistency() {
		// Create a test order
		$order = wc_create_order();
		$order->set_total( 100.00 );
		$order->set_currency( 'USD' );
		$order->save();

		// Mock API client to capture idempotency keys
		$api_client = $this->get_mock_api_client();
		$captured_keys = [];

		// Override the make_request method to capture idempotency keys
		$api_client->method( 'make_request' )
			->willReturnCallback( function( $method, $path, $params, $headers ) use ( &$captured_keys ) {
				if ( isset( $headers['Idempotency-Key'] ) ) {
					$captured_keys[] = $headers['Idempotency-Key'];
				}
				return [ 'id' => 'pi_test_' . uniqid() ];
			} );

		// Process payment multiple times
		$gateway = $this->get_mock_gateway( $api_client );
		
		// First attempt
		$gateway->process_payment( $order->get_id() );
		
		// Second attempt (should be prevented, but we can still check the key)
		$gateway->process_payment( $order->get_id() );

		// Verify that the same idempotency key was used for the same order
		$this->assertGreaterThan( 0, count( $captured_keys ) );
		
		// If multiple keys were captured, they should be the same for the same order
		if ( count( $captured_keys ) > 1 ) {
			$this->assertEquals( $captured_keys[0], $captured_keys[1] );
		}
	}

	/**
	 * Test webhook processing with duplicate payment intents.
	 */
	public function test_webhook_processing_with_duplicate_intents() {
		// Create a test order
		$order = wc_create_order();
		$order->set_total( 100.00 );
		$order->set_currency( 'USD' );
		$order->save();

		// Simulate a successful payment intent
		$intent_id = 'pi_test_intent_123';
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->save();

		// Mock webhook data
		$webhook_data = [
			'id'   => 'evt_test_webhook_123',
			'type' => 'payment_intent.succeeded',
			'data' => [
				'object' => [
					'id'       => $intent_id,
					'status'   => 'succeeded',
					'metadata' => [
						'order_id' => $order->get_id(),
					],
				],
			],
		];

		// Process the webhook
		$webhook_handler = new WC_Payments_Webhook_Handler();
		$result = $webhook_handler->process_webhook( $webhook_data );

		// Verify the webhook was processed successfully
		$this->assertTrue( $result );

		// Verify the order status was updated
		$order = wc_get_order( $order->get_id() );
		$this->assertEquals( 'processing', $order->get_status() );
	}

	/**
	 * Test manual retry scenario simulation.
	 */
	public function test_manual_retry_scenario_simulation() {
		// Create a test subscription and renewal order
		$subscription = $this->create_test_subscription();
		$renewal_order = $this->create_test_renewal_order( $subscription );

		// Simulate failed payment
		$renewal_order->update_status( 'failed' );

		// Mock the gateway for manual retry
		$gateway = $this->get_mock_gateway();

		// First manual retry attempt
		$result1 = $gateway->process_payment( $renewal_order->get_id() );
		$this->assertEquals( 'success', $result1['result'] );

		// Second manual retry attempt (should be prevented)
		$result2 = $gateway->process_payment( $renewal_order->get_id() );
		$this->assertEquals( 'fail', $result2['result'] );
		$this->assertStringContainsString( 'already been paid', $result2['messages'] );

		// Verify only one successful payment was recorded
		$renewal_order = wc_get_order( $renewal_order->get_id() );
		$this->assertEquals( 'processing', $renewal_order->get_status() );
	}

	/**
	 * Test error handling and cleanup.
	 */
	public function test_error_handling_and_cleanup() {
		// Create a test order
		$order = wc_create_order();
		$order->set_total( 100.00 );
		$order->set_currency( 'USD' );
		$order->save();

		// Mock the gateway to throw an exception
		$gateway = $this->get_mock_gateway();
		$gateway->method( 'process_payment_for_order' )
			->willThrowException( new Exception( 'Payment processing failed' ) );

		// Payment attempt should fail but unlock the order
		$result = $gateway->process_payment( $order->get_id() );
		$this->assertEquals( 'fail', $result['result'] );

		// Verify the order is unlocked (can be processed again)
		$duplicate_prevention_service = new WCPay\Duplicate_Payment_Prevention_Service();
		$can_lock = $duplicate_prevention_service->lock_order_for_payment_processing( $order );
		$this->assertTrue( $can_lock );

		// Clean up
		$duplicate_prevention_service->unlock_order_for_payment_processing( $order );
	}

	/**
	 * Helper method to create a mock gateway.
	 */
	private function get_mock_gateway( $api_client = null ) {
		$gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		
		if ( $api_client ) {
			$gateway->method( 'get_api_client' )->willReturn( $api_client );
		}

		$gateway->method( 'process_payment_for_order' )
			->willReturn( [
				'result'   => 'success',
				'redirect' => 'https://example.com/success',
			] );

		return $gateway;
	}

	/**
	 * Helper method to create a mock API client.
	 */
	private function get_mock_api_client() {
		$api_client = $this->createMock( WC_Payments_API_Client::class );
		
		$api_client->method( 'make_request' )
			->willReturn( [ 'id' => 'pi_test_' . uniqid() ] );

		return $api_client;
	}

	/**
	 * Helper method to create a test subscription.
	 */
	private function create_test_subscription() {
		// This would need to be implemented based on your subscription setup
		// For now, return a mock
		return $this->createMock( WC_Subscription::class );
	}

	/**
	 * Helper method to create a test renewal order.
	 */
	private function create_test_renewal_order( $subscription ) {
		$order = wc_create_order();
		$order->set_total( 50.00 );
		$order->set_currency( 'USD' );
		$order->save();

		// Add subscription relationship
		$order->update_meta_data( '_subscription_id', $subscription->get_id() );
		$order->save();

		return $order;
	}
}
