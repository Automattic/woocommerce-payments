<?php
/**
 * Class WC_Payments_Invoice_Service_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\API_Exception;

/**
 * WC_Payments_Invoice_Service_Test unit tests.
 */
class WC_Payments_Subscription_Change_Payment_Method_Test extends WP_UnitTestCase {

	/**
	 * System under test.
	 *
	 * @var WC_Payments_Product_Service
	 */
	private $change_payment_method_handler;

	/**
	 * Pre-test setup
	 */
	public function setUp() {
		parent::setUp();

		$this->change_payment_method_handler = new WC_Payments_Subscription_Change_Payment_Method_Handler();
	}

	/**
	 * Tests for WC_Payments_Subscription_Change_Payment_Method_Handler::update_subscription_change_payment_button().
	 */
	public function test_update_subscription_change_payment_button() {
		$mock_subscription = new WC_Subscription();

		// Test the false case - no change to input.
		$this->assertSame( [], $this->change_payment_method_handler->update_subscription_change_payment_button( [], $mock_subscription ) );

		// Set up a subscriotion with a failed last payment to test the positive/true case.
		$mock_subscription->status     = 'on-hold';
		$mock_subscription->last_order = WC_Helper_Order::create_order();
		$mock_subscription->last_order->set_status( 'failed' );

		// The update_subscription_change_payment_button function attempts to call WC_Subscription->get_checkout_payment_url(). To avoid errors mock that function's return.
		$mock_subscription->checkout_payment_url = 'example.com';
		$mock_subscription->update_meta_data( WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY, 'sub_test123' );
		$mock_subscription->save();

		$default_actions = [
			'suspend'               => [
				'name' => 'suspend',
				'url'  => 'example.com',
			],
			'change_payment_method' => [
				'name' => 'Change payment',
				'url'  => 'example.com',
			],
		];

		$result = $this->change_payment_method_handler->update_subscription_change_payment_button( $default_actions, $mock_subscription );

		$this->assertTrue( is_array( $result ) );
		$this->assertContainsOnly( 'array', $result );
		$this->assertCount( 2, $result );

		$this->assertArrayHasKey( 'change_payment_method', $result );

		// Confirm the change payment method element format.
		$this->assertCount( 2, $result['change_payment_method'] );
		$this->assertArrayHasKey( 'url', $result['change_payment_method'] );
		$this->assertArrayHasKey( 'name', $result['change_payment_method'] );

		// Confirm the function changed the button name and URL.
		$this->assertSame( 'Update payment method', $result['change_payment_method']['name'] );
		$this->assertRegExp( '/change_payment_method=/', $result['change_payment_method']['url'] );
		$this->assertRegExp( '/_wpnonce=/', $result['change_payment_method']['url'] );
	}

	/**
	 * Tests for WC_Payments_Subscription_Change_Payment_Method_Handler::update_order_pay_button().
	 */
	public function test_update_order_pay_button() {
		$mock_subscription = new WC_Subscription();
		$mock_order        = WC_Helper_Order::create_order();
		$test_actions      = [
			'cancel' => [
				'url' => 'example.com',
			],
		];

		$mock_subscription->set_parent( $mock_order );

		// Without a 'pay' key element, the array input should remain unchanged.
		$this->assertSame( [], $this->change_payment_method_handler->update_order_pay_button( [], $mock_order ) );
		$this->assertSame( $test_actions, $this->change_payment_method_handler->update_order_pay_button( $test_actions, $mock_order ) );

		// Add the 'pay' element.
		$test_actions['pay']['url'] = 'example.com?pay=123';
		$this->assertSame( $test_actions, $this->change_payment_method_handler->update_order_pay_button( $test_actions, $mock_order ) );

		// Set up the positive/true case.
		$mock_order->update_meta_data( WC_Payments_Invoice_Service::ORDER_INVOICE_ID_KEY, 'in_test123' );
		$mock_order->save();

		$mock_subscription->update_meta_data( WC_Payments_Invoice_Service::PENDING_INVOICE_ID_KEY, 'in_test123' );
		$mock_subscription->save();
		$this->mock_wcs_get_subscriptions_for_order( [ $mock_subscription ] );

		$result = $this->change_payment_method_handler->update_order_pay_button( $test_actions, $mock_order );

		// Confirm the order actions format.
		$this->assertContainsOnly( 'array', $result );
		$this->assertSame( count( $test_actions ), count( $result ) );

		$this->assertArrayHasKey( 'cancel', $test_actions );
		$this->assertArrayHasKey( 'pay', $test_actions );

		// Confirm the pay url has been updated to include the change payment method flag.
		$this->assertRegExp( '/order-pay=/', $result['pay']['url'] );
		$this->assertRegExp( '/pay_for_order=/', $result['pay']['url'] );
		$this->assertRegExp( '/change_payment_method=/', $result['pay']['url'] );
	}

	/**
	 * Tests for WC_Payments_Subscription_Change_Payment_Method_Handler::can_update_payment_method().
	 */
	public function test_can_update_payment_method() {
		$mock_subscription = new WC_Subscription();
		$mock_order        = WC_Helper_Order::create_order();

		$mock_subscription->last_order = $mock_order;
		$mock_subscription->update_meta_data( WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY, 'sub_test123' );
		$mock_subscription->save();

		// Confirm the input is unchanged on the negative case.
		foreach ( [ false, true ] as $input ) {
			$this->assertEquals( $input, $this->change_payment_method_handler->can_update_payment_method( $input, $mock_subscription ) );
		}

		$mock_subscription->status = 'on-hold';
		$this->assertFalse( $this->change_payment_method_handler->can_update_payment_method( false, $mock_subscription ) );
		$this->assertTrue( $this->change_payment_method_handler->can_update_payment_method( true, $mock_subscription ) );

		// Set up the positive case - last order is failed.
		$mock_order->set_status( 'failed' );
		$mock_order->save();
		$this->assertTrue( $this->change_payment_method_handler->can_update_payment_method( true, $mock_subscription ) );
		$this->assertTrue( $this->change_payment_method_handler->can_update_payment_method( false, $mock_subscription ) );
	}

	/**
	 * Tests for WC_Payments_Subscription_Change_Payment_Method_Handler::change_payment_method_page_title().
	 */
	public function test_change_payment_method_page_title() {
		$mock_subscription = new WC_Subscription();
		$mock_order        = WC_Helper_Order::create_order();
		$default_title     = 'Test Title';

		// Confirm the input is unchanged on the negative case.
		$this->assertSame( $default_title, $this->change_payment_method_handler->change_payment_method_page_title( $default_title, $mock_subscription ) );

		$mock_subscription->last_order = $mock_order;
		$mock_subscription->update_meta_data( WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY, 'sub_test123' );
		$mock_subscription->save();

		// Confirm the input is unchanged on the negative case.
		$this->assertSame( $default_title, $this->change_payment_method_handler->change_payment_method_page_title( $default_title, $mock_subscription ) );

		$mock_subscription->status = 'on-hold';
		$this->assertSame( $default_title, $this->change_payment_method_handler->change_payment_method_page_title( $default_title, $mock_subscription ) );

		// Set up the positive case - last order is failed.
		$mock_order->set_status( 'failed' );
		$mock_order->save();
		$this->assertSame( 'Update payment details', $this->change_payment_method_handler->change_payment_method_page_title( $default_title, $mock_subscription ) );
	}

	/**
	 * Mocks the wcs_get_subscriptions_for_order function return.
	 *
	 * @param WC_Subscription[] $subscriptions The subscriptions to return to wcs_get_subscriptions_for_order calls.
	 */
	private function mock_wcs_get_subscriptions_for_order( $subscriptions ) {
		WC_Subscriptions::set_wcs_get_subscriptions_for_order(
			function ( $order ) use ( $subscriptions ) {
				return $subscriptions;
			}
		);
	}

	/**
	 * Tests for WC_Payments_Subscription_Change_Payment_Method_Handler::change_payment_method_form_submit_text().
	 */
	public function test_change_payment_method_form_submit_text() {
		$mock_subscription = new WC_Subscription();
		$mock_order        = WC_Helper_Order::create_order();
		$default_text      = 'Change payment';

		// Confirm the input is unchanged on the negative case.
		$this->assertSame( $default_text, $this->change_payment_method_handler->change_payment_method_form_submit_text( $default_text ) );

		// Simulate the change payment method request.
		$_GET['change_payment_method'] = 10;

		// Mock the wcs_get_subscription function to return our mock subscription.
		WC_Subscriptions::set_wcs_get_subscription(
			function ( $id ) use ( $mock_subscription ) {
				return $mock_subscription;
			}
		);

		$mock_subscription->last_order = $mock_order;
		$mock_subscription->update_meta_data( WC_Payments_Subscription_Service::SUBSCRIPTION_ID_META_KEY, 'sub_test123' );
		$mock_subscription->save();

		// Confirm the input is unchanged on the negative case.
		$this->assertSame( $default_text, $this->change_payment_method_handler->change_payment_method_form_submit_text( $default_text ) );

		$mock_subscription->status = 'on-hold';
		$this->assertSame( $default_text, $this->change_payment_method_handler->change_payment_method_form_submit_text( $default_text ) );

		// Set up the positive case - last order is failed.
		$mock_order->set_status( 'failed' );
		$mock_order->save();
		$this->assertSame( 'Update and retry payment', $this->change_payment_method_handler->change_payment_method_form_submit_text( $default_text ) );
	}
}
