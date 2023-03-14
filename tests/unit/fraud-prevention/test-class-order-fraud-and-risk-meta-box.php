<?php
/**
 * Class Order_Fraud_And_Risk_Meta_Box_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Fraud_Prevention\Order_Fraud_And_Risk_Meta_Box;

/**
 * Order_Fraud_And_Risk_Meta_Box_Test unit tests.
 */
class Order_Fraud_And_Risk_Meta_Box_Test extends WCPAY_UnitTestCase {

	/**
	 *
	 *
	 * @var Order_Fraud_And_Risk_Meta_Box
	 */
	private $order_fraud_and_risk_meta_box;

	public function set_up() {
		parent::set_up();

		$this->order_fraud_and_risk_meta_box = new Order_Fraud_And_Risk_Meta_Box();
	}

	public function test_display_order_fraud_and_risk_meta_box_message_display_passed_message() {
		// Arrange.
		$order = $this->get_test_order( [ '_wcpay_fraud_outcome_status' => 'passed' ] );

		// Assert.
		$this->expectOutputString( '<p class="wcpay-fraud-risk-meta-passed">No action taken</p><p>The payment for this order passed your risk filtering.</p>' );

		// Act.
		$this->order_fraud_and_risk_meta_box->display_order_fraud_and_risk_meta_box_message( $order );
	}

	public function test_display_order_fraud_and_risk_meta_box_message_display_review_message() {
		// Arrange.
		$order = $this->get_test_order( [ '_wcpay_fraud_outcome_status' => 'review' ] );

		// Assert.
		$this->expectOutputString( '<p class="wcpay-fraud-risk-meta-held">Held for review</p><p>The payment for this order was held for review by your risk filtering. You can review the details and determine whether to approve or block the payment.</p><a href="http://example.org/wp-admin/admin.php?page=wc-admin&#038;path=/payments/transactions/details&#038;id=pi_123" target="_blank" rel="noopener noreferrer">Review payment</a>' );

		// Act.
		$this->order_fraud_and_risk_meta_box->display_order_fraud_and_risk_meta_box_message( $order );
	}

	public function test_display_order_fraud_and_risk_meta_box_message_display_blocked_message() {
		// Arrange.
		$order = $this->get_test_order( [ '_wcpay_fraud_outcome_status' => 'block' ] );

		// Assert.
		$this->expectOutputString( '<p class="wcpay-fraud-risk-meta-blocked">Blocked</p><p>The payment for this order was blocked by your risk filtering, and the order has been cancelled.</p><a href="http://example.org/wp-admin/admin.php?page=wc-admin&#038;path=/payments/transactions/details&#038;id=pi_123" target="_blank" rel="noopener noreferrer">View payment details</a>' );

		// Act.
		$this->order_fraud_and_risk_meta_box->display_order_fraud_and_risk_meta_box_message( $order );
	}

	public function test_display_order_fraud_and_risk_meta_box_message_display_not_wcpay_message() {
		// Arrange.
		$order = $this->get_test_order( [], 'not-wcpay' );

		// Assert.
		$this->expectOutputString( '<p>Risk filtering is only available for orders that are processed with WooCommerce Payments.</p><a href="" target="_blank" rel="noopener noreferrer">Learn more</a>' );

		// Act.
		$this->order_fraud_and_risk_meta_box->display_order_fraud_and_risk_meta_box_message( $order );
	}

	public function test_display_order_fraud_and_risk_meta_box_message_display_no_risk_filtering_message() {
		// Arrange.
		$order = $this->get_test_order();

		// Assert.
		$this->expectOutputString( '<p>Risk filtering through WooCommerce Payments was not found on this order, it may have been created while filtering was not enabled.</p>' );

		// Act.
		$this->order_fraud_and_risk_meta_box->display_order_fraud_and_risk_meta_box_message( $order );
	}

	/**
	 * Creates an order with meta data and the defined payment method.
	 *
	 * @param array $meta_data The key => value pairs for meta data to be added.
	 * @param string $payment_method The payment method to use for the order, defaults to woocommerce_payments
	 *
	 * @return WC_Order
	 */
	private function get_test_order( $meta_data = [], $payment_method = 'woocommerce_payments' ) {
		// Create the order and set the payment method.
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( $payment_method );

		// Set the default meta, merge the passed meta data in, then add it to the order.
		$default_meta = [
			'_intent_id' => 'pi_123',
			'_charge_id' => 'pi_123',
		];
		$meta_data    = array_merge( $default_meta, $meta_data );
		foreach ( $meta_data as $meta_key => $meta_value ) {
			$order->update_meta_data( $meta_key, $meta_value );
		}

		// Save the order and return it.
		$order->save();
		return $order;
	}
}
