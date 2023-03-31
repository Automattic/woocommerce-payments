<?php
/**
 * Class Order_Fraud_And_Risk_Meta_Box_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Constants\Fraud_Meta_Box_Type;
use WCPay\Fraud_Prevention\Order_Fraud_And_Risk_Meta_Box;

/**
 * Fraud_Prevention_Service_Test unit tests.
 */
class Order_Fraud_And_Risk_Meta_Box_Test extends WCPAY_UnitTestCase {

	/**
	 * Test WC_Order object.
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Order_Fraud_And_Risk_Meta_Box object.
	 *
	 * @var Order_Fraud_And_Risk_Meta_Box
	 */
	private $order_fraud_and_risk_meta_box;

	/**
	 * WC_Payments_Order_Service mock object.
	 *
	 * @var PHPUnit_Framework_MockObject_MockObject|WC_Payments_Order_Service
	 */
	private $mock_order_service;

	public function set_up() {
		parent::set_up();

		// Create the mock Order Service and the Fraud and Risk meta box objects.
		$this->mock_order_service            = $this->createMock( WC_Payments_Order_Service::class );
		$this->order_fraud_and_risk_meta_box = new Order_Fraud_And_Risk_Meta_Box( $this->mock_order_service );

		// Create the mock order and set the gateway.
		$this->order = WC_Helper_Order::create_order();
		$this->order->set_payment_method( 'woocommerce_payments' );
		$this->order->save();
	}

	/**
	 * @dataProvider display_order_fraud_and_risk_meta_box_message_provider
	 */
	public function test_display_order_fraud_and_risk_meta_box_message( $meta_box_type, $expected_output ) {
		// Arrange: Set the return results for the order service methods.
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_intent_id_for_order' )
			->willReturn( 'pi_mock' );

		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_charge_id_for_order' )
			->willReturn( 'ch_mock' );

		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_fraud_meta_box_type_for_order' )
			->willReturn( $meta_box_type );

		// Act: Call the method to display the meta box.
		$this->order_fraud_and_risk_meta_box->display_order_fraud_and_risk_meta_box_message( $this->order );

		// Assert: Check to make sure the expected string has been output.
		$this->expectOutputString( $expected_output );
	}

	public function display_order_fraud_and_risk_meta_box_message_provider() {
		return [
			'Fraud_Meta_Box_Type_ALLOW'            => [
				'meta_box_type'   => Fraud_Meta_Box_Type::ALLOW,
				'expected_output' => '<p class="wcpay-fraud-risk-meta-allow"><img src="http://example.org/wp-content/plugins/Users/j/sites/wcp/wp-content/plugins/woocommerce-payments/assets/images/icons/check-green.svg" alt="Green check mark"> No action taken</p><p>The payment for this order passed your risk filtering.</p>',
			],
			'Fraud_Meta_Box_Type_PAYMENT_STARTED'  => [
				'meta_box_type'   => Fraud_Meta_Box_Type::PAYMENT_STARTED,
				'expected_output' => '<p class="wcpay-fraud-risk-meta-review"><img src="http://example.org/wp-content/plugins/Users/j/sites/wcp/wp-content/plugins/woocommerce-payments/assets/images/icons/shield-stroke-orange.svg" alt="Orange shield outline"> No action taken</p><p>The payment for this order has not yet been passed to the fraud and risk filters to determine its outcome status.</p>',
			],
			'Fraud_Meta_Box_Type_REVIEW'           => [
				'meta_box_type'   => Fraud_Meta_Box_Type::REVIEW,
				'expected_output' => '<p class="wcpay-fraud-risk-meta-review"><img src="http://example.org/wp-content/plugins/Users/j/sites/wcp/wp-content/plugins/woocommerce-payments/assets/images/icons/shield-stroke-orange.svg" alt="Orange shield outline"> Held for review</p><p>The payment for this order was held for review by your risk filtering. You can review the details and determine whether to approve or block the payment.</p><a href="http://example.org/wp-admin/admin.php?page=wc-admin&#038;path=/payments/transactions/details&#038;id=pi_mock" target="_blank" rel="noopener noreferrer">Review payment</a>',
			],
			'Fraud_Meta_Box_Type_REVIEW_ALLOWED'   => [
				'meta_box_type'   => Fraud_Meta_Box_Type::REVIEW_ALLOWED,
				'expected_output' => '<p class="wcpay-fraud-risk-meta-allow"><img src="http://example.org/wp-content/plugins/Users/j/sites/wcp/wp-content/plugins/woocommerce-payments/assets/images/icons/check-green.svg" alt="Green check mark"> Held for review</p><p>This transaction was held for review by your risk filters, and the charge was manually approved after review.</p><a href="http://example.org/wp-admin/admin.php?page=wc-admin&#038;path=/payments/transactions/details&#038;id=pi_mock" target="_blank" rel="noopener noreferrer">Review payment</a>',
			],
			'Fraud_Meta_Box_Type_REVIEW_CANCELLED' => [
				'meta_box_type'   => Fraud_Meta_Box_Type::REVIEW_CANCELLED,
				'expected_output' => '<p class="wcpay-fraud-risk-meta-review"><img src="http://example.org/wp-content/plugins/Users/j/sites/wcp/wp-content/plugins/woocommerce-payments/assets/images/icons/shield-stroke-orange.svg" alt="Orange shield outline"> Held for review</p><p>The payment for this order was held for review by your risk filtering. The charge appears to have been cancelled.</p><a href="http://example.org/wp-admin/admin.php?page=wc-admin&#038;path=/payments/transactions/details&#038;id=pi_mock" target="_blank" rel="noopener noreferrer">Review payment</a>',
			],
			'Fraud_Meta_Box_Type_REVIEW_EXPIRED'   => [
				'meta_box_type'   => Fraud_Meta_Box_Type::REVIEW_EXPIRED,
				'expected_output' => '<p class="wcpay-fraud-risk-meta-review"><img src="http://example.org/wp-content/plugins/Users/j/sites/wcp/wp-content/plugins/woocommerce-payments/assets/images/icons/shield-stroke-orange.svg" alt="Orange shield outline"> Held for review</p><p>The payment for this order was held for review by your risk filtering. The authorization for the charge appears to have expired.</p><a href="http://example.org/wp-admin/admin.php?page=wc-admin&#038;path=/payments/transactions/details&#038;id=pi_mock" target="_blank" rel="noopener noreferrer">Review payment</a>',
			],
			'Fraud_Meta_Box_Type_REVIEW_FAILED'    => [
				'meta_box_type'   => Fraud_Meta_Box_Type::REVIEW_FAILED,
				'expected_output' => '<p class="wcpay-fraud-risk-meta-review"><img src="http://example.org/wp-content/plugins/Users/j/sites/wcp/wp-content/plugins/woocommerce-payments/assets/images/icons/shield-stroke-orange.svg" alt="Orange shield outline"> Held for review</p><p>The payment for this order was held for review by your risk filtering. The authorization for the charge appears to have failed.</p><a href="http://example.org/wp-admin/admin.php?page=wc-admin&#038;path=/payments/transactions/details&#038;id=pi_mock" target="_blank" rel="noopener noreferrer">Review payment</a>',
			],
		];
	}

	public function test_display_order_fraud_and_risk_meta_box_message_exits_if_no_order() {
		// Arrange: Set the return results for the order service methods.
		$this->mock_order_service
			->expects( $this->never() )
			->method( 'get_intent_id_for_order' );

		$this->mock_order_service
			->expects( $this->never() )
			->method( 'get_charge_id_for_order' );

		$this->mock_order_service
			->expects( $this->never() )
			->method( 'get_fraud_meta_box_type_for_order' );

		// Act: Call the method to display the meta box.
		$this->order_fraud_and_risk_meta_box->display_order_fraud_and_risk_meta_box_message( 'fake_order' );

		// Assert: Check to make sure the expected string has been output.
		$this->expectOutputString( '' );
	}

	public function test_display_order_fraud_and_risk_meta_box_message_block() {
		// Arrange: Set the return results for the order service methods.
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_intent_id_for_order' )
			->willReturn( 'pi_mock' );

		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_charge_id_for_order' )
			->willReturn( 'ch_mock' );

		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_fraud_meta_box_type_for_order' )
			->willReturn( Fraud_Meta_Box_Type::BLOCK );

		// Act: Call the method to display the meta box.
		$this->order_fraud_and_risk_meta_box->display_order_fraud_and_risk_meta_box_message( $this->order );

		// Assert: Check to make sure the expected string has been output.
		$this->expectOutputString( '<p class="wcpay-fraud-risk-meta-blocked"><img src="http://example.org/wp-content/plugins/Users/j/sites/wcp/wp-content/plugins/woocommerce-payments/assets/images/icons/shield-stroke-red.svg" alt="Red shield outline"> Blocked</p><p>The payment for this order was blocked by your risk filtering. There is no pending authorization, and the order can be cancelled to reduce any held stock.</p><a href="http://example.org/wp-admin/admin.php?page=wc-admin&#038;path=/payments/transactions/details&#038;id=' . $this->order->get_id() . '" target="_blank" rel="noopener noreferrer">View more details</a>' );
	}

	public function test_display_order_fraud_and_risk_meta_box_message_not_wcpay() {
		// Arrange: Set the return results for the order service methods.
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_intent_id_for_order' )
			->willReturn( '' );

		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_charge_id_for_order' )
			->willReturn( '' );

		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_fraud_meta_box_type_for_order' )
			->willReturn( '' );

		// Arrange: Update the order's payment method.
		$this->order->set_payment_method( 'bacs' );
		$this->order->save();

		// Act: Call the method to display the meta box.
		$this->order_fraud_and_risk_meta_box->display_order_fraud_and_risk_meta_box_message( $this->order );

		// Assert: Check to make sure the expected string has been output.
		$this->expectOutputString( '<p>Risk filtering is only available for orders that are paid for with credit cards through WooCommerce Payments.</p><a href="" target="_blank" rel="noopener noreferrer">Learn more</a>' );
	}

	public function test_display_order_fraud_and_risk_meta_box_message_default() {
		// Arrange: Set the return results for the order service methods.
		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_intent_id_for_order' )
			->willReturn( 'pi_mock' );

		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_charge_id_for_order' )
			->willReturn( 'ch_mock' );

		$this->mock_order_service
			->expects( $this->once() )
			->method( 'get_fraud_meta_box_type_for_order' )
			->willReturn( '' );

		// Act: Call the method to display the meta box.
		$this->order_fraud_and_risk_meta_box->display_order_fraud_and_risk_meta_box_message( $this->order );

		// Assert: Check to make sure the expected string has been output.
		$this->expectOutputString( '<p>Risk filtering through WooCommerce Payments was not found on this order, it may have been created while filtering was not enabled.</p>' );
	}
}
