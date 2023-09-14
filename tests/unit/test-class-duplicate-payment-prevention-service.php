<?php
/**
 * Class WC_Payment_Gateway_WCPay_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Order_Status;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Duplicate_Payment_Prevention_Service;

/**
 * WCPay\Duplicate_Payment_Prevention_Service unit tests.
 */
class Duplicate_Payment_Prevention_Service_Test extends WCPAY_UnitTestCase {
	/**
	 * System under test.
	 *
	 * @var Duplicate_Payment_Prevention_Service
	 */
	private $service;

	/**
	 * Order service mock.
	 *
	 * @var WC_Payments_Order_Service
	 */
	private $mock_order_service;

	/**
	 * Gateway mock.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $mock_gateway;

	/**
	 * Pre-test setup
	 */
	public function set_up() {
		parent::set_up();

		$this->mock_order_service = $this->createMock( WC_Payments_Order_Service::class );
		$this->mock_gateway       = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->service            = new Duplicate_Payment_Prevention_Service();
		$this->service->init( $this->mock_gateway, $this->mock_order_service );
	}

	public function test_check_session_order_redirect_to_previous_order() {
		$same_cart_hash = 'FAKE_SAME_CART_HASH';
		$return_url     = 'https://example.com';

		// Arrange the order saved in the session.
		$session_order = WC_Helper_Order::create_order();
		$session_order->set_cart_hash( $same_cart_hash );
		$session_order->set_status( Order_Status::COMPLETED );
		$session_order->save();
		WC()->session->set(
			Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER,
			$session_order->get_id()
		);

		// Arrange the order is being processed.
		$current_order = WC_Helper_Order::create_order();
		$current_order->set_cart_hash( $same_cart_hash );
		$current_order->save();
		$current_order_id = $current_order->get_id();

		// Arrange the redirect URL.
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_return_url' )
			->willReturn( $return_url );

		// Act: process the order but redirect to the previous/session paid order.
		$result = $this->service->check_against_session_processing_order( $current_order );

		// Assert: the result of check_against_session_processing_order.
		$this->assertSame( 'yes', $result['wcpay_upe_paid_for_previous_order'] );
		$this->assertSame( 'success', $result['result'] );
		$this->assertStringContainsString( $return_url, $result['redirect'] );

		// Assert: the behaviors of check_against_session_processing_order.
		$notes = wc_get_order_notes( [ 'order_id' => $session_order->get_id() ] );
		$this->assertStringContainsString(
			'WooCommerce Payments: detected and deleted order ID ' . $current_order_id,
			$notes[0]->content
		);
		$this->assertSame( Order_Status::TRASH, wc_get_order( $current_order_id )->get_status() );
		$this->assertSame(
			null,
			WC()->session->get( Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER )
		);
	}

	public function test_maybe_update_session_processing_order_stores_order() {
		// Arrange the order saved in the session.
		WC()->session->set(
			Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER,
			null
		);

		// Arrange the order is being processed.
		$current_order    = WC_Helper_Order::create_order();
		$current_order_id = $current_order->get_id();

		// Act: call maybe_update_session_processing_order to store the order in session.
		$this->service->maybe_update_session_processing_order( $current_order_id );

		// Assert: maybe_update_session_processing_order takes action and its value is kept.
		$this->assertSame(
			$current_order_id,
			WC()->session->get( Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER )
		);

		// Destroy the session value after running test.
		WC()->session->set(
			Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER,
			null
		);
	}

	/**
	 * @dataProvider provider_process_payment_check_session_and_continue_processing
	 */
	public function test_check_session_without_redirection( string $session_order_cart_hash, string $session_order_status, string $current_order_cart_hash ) {
		// Arrange the order saved in the session.
		$session_order = WC_Helper_Order::create_order();
		$session_order->set_cart_hash( $session_order_cart_hash );
		$session_order->set_status( $session_order_status );
		$session_order->save();
		WC()->session->set(
			Duplicate_Payment_Prevention_Service::SESSION_KEY_PROCESSING_ORDER,
			$session_order->get_id()
		);

		// Arrange the order is being processed.
		$current_order = WC_Helper_Order::create_order();
		$current_order->set_cart_hash( $current_order_cart_hash );
		$current_order->save();

		// Act.
		$result = $this->service->check_against_session_processing_order( $current_order );

		// Assert: no redirect was generated.
		$this->assertNull( $result );
	}

	public function provider_process_payment_check_session_and_continue_processing() {
		return [
			'Different cart hash with session order status completed'   => [ 'SESSION_ORDER_HASH', Order_Status::COMPLETED, 'CURRENT_ORDER_HASH' ],
			'Different cart hash  with session order status processing' => [ 'SESSION_ORDER_HASH', Order_Status::PROCESSING, 'CURRENT_ORDER_HASH' ],
			'Same cart hash with session order status pending'          => [ 'SAME_CART_HASH', Order_Status::PENDING, 'SAME_CART_HASH' ],
			'Same cart hash with session order status cancelled'        => [ 'SAME_CART_HASH', Order_Status::CANCELLED, 'SAME_CART_HASH' ],
		];
	}

	/**
	 * @dataProvider provider_test_check_payment_intent_attached_to_order_succeeded_with_invalid_intent_id_returns_null
	 * @param ?string $invalid_intent_id An invalid payment intent ID. If no intent id is set, this can be null.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_with_invalid_intent_id_returns_null( $invalid_intent_id ) {
		// Arrange order.
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $invalid_intent_id );
		$order->save();

		// Assert: get_intent is not called.
		$this->mock_wcpay_request( Get_Intention::class, 0 );

		// Act: process the order.
		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );

		// Assert: No redirect was generated.
		$this->assertNull( $result );
	}

	public function provider_test_check_payment_intent_attached_to_order_succeeded_with_invalid_intent_id_returns_null(): array {
		return [
			'No intent_id is attached'   => [ null ],
			'A setup intent is attached' => [ 'seti_possible_for_a_subscription_id' ],
		];
	}

	/**
	 * The attached PaymentIntent has invalid info (status or order_id) with the order, so payment_process continues.
	 *
	 * @dataProvider provider_check_payment_intent_attached_to_order_succeeded_with_invalid_data_returns_null
	 * @param  string  $attached_intent_id Attached intent ID to the order.
	 * @param  string  $attached_intent_status Attached intent status.
	 * @param  bool  $same_order_id True when the intent meta order_id is exactly the current processing order_id. False otherwise.
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_with_invalid_data_returns_null(
		string $attached_intent_id,
		string $attached_intent_status,
		bool $same_order_id
	) {
		// Arrange order.
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $attached_intent_id );
		$order->save();

		$order_id = $order->get_id();

		// Arrange mock get_intent.
		$meta_order_id   = $same_order_id ? $order_id : $order_id - 1;
		$attached_intent = WC_Helper_Intention::create_intention(
			[
				'id'       => $attached_intent_id,
				'status'   => $attached_intent_status,
				'metadata' => [ 'order_id' => $meta_order_id ],
			]
		);
		$this->mock_wcpay_request( Get_Intention::class, 1, $attached_intent_id )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $attached_intent );

		// Act: process the order.
		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );
		$this->assertNull( $result );
	}

	public function provider_check_payment_intent_attached_to_order_succeeded_with_invalid_data_returns_null(): array {
		return [
			'Attached PaymentIntent with non-success status - same order_id' => [ 'pi_attached_intent_id', 'requires_action', true ],
			'Attached PaymentIntent - non-success status - different order_id' => [ 'pi_attached_intent_id', 'requires_action', false ],
			'Attached PaymentIntent - success status - different order_id' => [ 'pi_attached_intent_id', 'succeeded', false ],
		];
	}

	/**
	 * @dataProvider provider_check_payment_intent_attached_to_order_succeeded_return_redirection
	 */
	public function test_check_payment_intent_attached_to_order_succeeded_return_redirection( string $intent_successful_status ) {
		$attached_intent_id = 'pi_attached_intent_id';
		$return_url         = 'https://example.com';

		// Arrange the redirect URL.
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_return_url' )
			->willReturn( $return_url );

		// Arrange order.
		$order = WC_Helper_Order::create_order();
		$order->update_meta_data( '_intent_id', $attached_intent_id );
		$order->save();
		$order_id = $order->get_id();

		// Arrange mock get_intention.
		$attached_intent = WC_Helper_Intention::create_intention(
			[
				'id'       => $attached_intent_id,
				'status'   => $intent_successful_status,
				'metadata' => [ 'order_id' => $order_id ],
			]
		);

		$this->mock_wcpay_request( Get_Intention::class, 1, $attached_intent_id )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $attached_intent );

		// Act: process the order but redirect to the order.
		$result = $this->service->check_payment_intent_attached_to_order_succeeded( $order );

		// Assert: the result of check_intent_attached_to_order_succeeded.
		$this->assertSame( 'yes', $result['wcpay_upe_previous_successful_intent'] );
		$this->assertSame( 'success', $result['result'] );
		$this->assertStringContainsString( $return_url, $result['redirect'] );
	}

	public function provider_check_payment_intent_attached_to_order_succeeded_return_redirection(): array {
		$ret = [];
		foreach ( WC_Payment_Gateway_WCPay::SUCCESSFUL_INTENT_STATUS as $status ) {
			$ret[ 'Intent status ' . $status ] = [ $status ];
		}

		return $ret;
	}
}
