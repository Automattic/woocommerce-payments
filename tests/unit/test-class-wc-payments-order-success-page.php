<?php
/**
 * Class WC_Payments_Order_Success_Page_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use WCPay\Payment_Methods\UPE_Payment_Method;
use WCPay\Core\Server\Request\Get_Intention;
use WCPay\Constants\Payment_Method;

/**
 * WC_Payments_Order_Success_Page unit tests.
 */
class WC_Payments_Order_Success_Page_Test extends WCPAY_UnitTestCase {
	/**
	 * WC_Payments_Order_Success_Page Instance.
	 *
	 * @var WC_Payments_Order_Success_Page
	 */
	private $payments_order_success_page;

	public function set_up() {
		parent::set_up();

		$this->payments_order_success_page = new WC_Payments_Order_Success_Page();
		$this->payments_order_success_page->init_hooks();
	}

	public function tear_down() {
		global $wp;
		unset( $_GET['key'] );
		unset( $wp->query_vars['order-received'] );
		remove_action( 'wcpay_internal_last_intent_id', [ $this->payments_order_success_page, 'remember_paid_intent_id' ] );
		if ( WC()->session ) {
			WC()->session->set( WC_Payments_Order_Success_Page::SESSION_KEY_PAID_INTENT_ID, null );
		}
		parent::tear_down();
	}

	/**
	 * Builds a WooPayments order with the given intent attached.
	 *
	 * @param string $intent_id The payment intent id to attach to the order.
	 * @return WC_Order
	 */
	private function create_paid_woopayments_order( $intent_id = 'pi_abc123' ) {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->update_meta_data( '_intent_id', $intent_id );
		$order->set_date_paid( time() );
		$order->save();

		return $order;
	}

	/**
	 * Records the intent the current session paid, mirroring what the gateway stores at payment time.
	 *
	 * @param string $intent_id The paid intent id.
	 */
	private function set_session_paid_intent_id( $intent_id ) {
		WC()->session->set( WC_Payments_Order_Success_Page::SESSION_KEY_PAID_INTENT_ID, $intent_id );
	}

	public function test_skips_email_verification_for_the_session_that_paid() {
		$order = $this->create_paid_woopayments_order( 'pi_abc123' );
		$this->set_session_paid_intent_id( 'pi_abc123' );

		$this->assertFalse(
			$this->payments_order_success_page->maybe_skip_email_verification_after_payment( true, $order, 'order-received' )
		);
	}

	public function test_keeps_email_verification_when_the_session_has_no_paid_intent() {
		// A bare order-key leak in another session carries no paid intent, so verification still applies.
		$order = $this->create_paid_woopayments_order( 'pi_abc123' );

		$this->assertTrue(
			$this->payments_order_success_page->maybe_skip_email_verification_after_payment( true, $order, 'order-received' )
		);
	}

	public function test_keeps_email_verification_when_the_session_intent_does_not_match_the_order() {
		$order = $this->create_paid_woopayments_order( 'pi_abc123' );
		$this->set_session_paid_intent_id( 'pi_someone_elses' );

		$this->assertTrue(
			$this->payments_order_success_page->maybe_skip_email_verification_after_payment( true, $order, 'order-received' )
		);
	}

	public function test_keeps_email_verification_outside_the_order_received_context() {
		$order = $this->create_paid_woopayments_order( 'pi_abc123' );
		$this->set_session_paid_intent_id( 'pi_abc123' );

		$this->assertTrue(
			$this->payments_order_success_page->maybe_skip_email_verification_after_payment( true, $order, 'order-pay' )
		);
	}

	public function test_payment_completed_action_records_the_intent_and_waives_verification() {
		$order = $this->create_paid_woopayments_order( 'pi_abc123' );

		// Fire the gateway's action; the success page listener (registered in init_hooks) stores the intent.
		do_action( 'wcpay_internal_last_intent_id', 'pi_abc123', $order ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment, WooCommerce.Commenting.CommentHooks.HookCommentWrongStyle -- Firing an existing action in a test.

		$this->assertSame(
			'pi_abc123',
			WC()->session->get( WC_Payments_Order_Success_Page::SESSION_KEY_PAID_INTENT_ID )
		);
		$this->assertFalse(
			$this->payments_order_success_page->maybe_skip_email_verification_after_payment( true, $order, 'order-received' )
		);
	}

	public function test_show_card_payment_method_name_without_card_brand() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->save();

		$payment_method = $this->getMockBuilder( '\WCPay\Payment_Methods\UPE_Payment_Method' )
			->disableOriginalConstructor()
			->getMock();
		$payment_method->method( 'get_title' )->willReturn( 'Credit Card' );

		$result = $this->payments_order_success_page->show_card_payment_method_name( $order, $payment_method );

		$this->assertEquals( 'Credit Card', $result );
	}

	public function test_show_card_payment_method_name_with_brand_and_last4() {
		$order = WC_Helper_Order::create_order();
		$order->add_meta_data( '_card_brand', 'visa' );
		$order->add_meta_data( 'last4', '4242' );
		$order->set_payment_method( 'woocommerce_payments' );
		$order->save();

		$payment_method = $this->getMockBuilder( '\WCPay\Payment_Methods\UPE_Payment_Method' )
			->disableOriginalConstructor()
			->getMock();
		$payment_method->method( 'get_title' )->willReturn( 'Credit Card' );

		$result = $this->payments_order_success_page->show_card_payment_method_name( $order, $payment_method );

		$this->assertStringContainsString( 'wc-payment-gateway-method-logo-wrapper wc-payment-card-logo', $result );
		$this->assertStringContainsString( 'img alt="Credit Card"', $result );
		$this->assertStringContainsString( 'visa.svg', $result );
		$this->assertStringContainsString( '4242', $result );
	}

	public function test_show_card_payment_method_name_with_brand_only() {
		$order = WC_Helper_Order::create_order();
		$order->add_meta_data( '_card_brand', 'mastercard' );
		$order->set_payment_method( 'woocommerce_payments' );
		$order->save();

		$payment_method = $this->getMockBuilder( '\WCPay\Payment_Methods\UPE_Payment_Method' )
			->disableOriginalConstructor()
			->getMock();
		$payment_method->method( 'get_title' )->willReturn( 'Credit Card' );

		$result = $this->payments_order_success_page->show_card_payment_method_name( $order, $payment_method );

		$this->assertStringContainsString( 'wc-payment-gateway-method-logo-wrapper wc-payment-card-logo', $result );
		$this->assertStringContainsString( 'img alt="Credit Card"', $result );
		$this->assertStringContainsString( 'mastercard.svg', $result );
		$this->assertStringNotContainsString( '•••', $result );
	}

	public function test_show_woopay_payment_method_name_empty_order() {
		$method_name = 'Credit card';
		$result      = $this->payments_order_success_page->show_woocommerce_payments_payment_method_name( $method_name, null );

		$this->assertSame( $method_name, $result );
	}

	public function test_show_woopay_payment_method_name_without_woopay_meta() {
		$order = WC_Helper_Order::create_order();
		$order->save();

		$method_name = 'Credit card';
		$result      = $this->payments_order_success_page->show_woocommerce_payments_payment_method_name( $method_name, $order );

		$this->assertSame( $method_name, $result );
	}

	public function test_show_lpm_payment_method_name() {
		$gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$gateway->method( 'get_account_country' )->willReturn( 'SG' );

		$payment_method = $this->createMock( UPE_Payment_Method::class );
		$payment_method->method( 'get_title' )->willReturn( 'GrabPay' );
		$payment_method->method( 'get_id' )->willReturn( 'grabpay' );
		$payment_method->method( 'get_icon' )->willReturn( '/grabpay.svg' );
		$payment_method->method( 'get_dark_icon' )->willReturn( '/grabpay.svg' );

		$result = $this->payments_order_success_page->show_lpm_payment_method_name( $gateway, $payment_method );

		$this->assertStringContainsString( 'wc-payment-gateway-method-logo-wrapper', $result );
		$this->assertStringContainsString( 'alt="GrabPay"', $result );
		$this->assertStringContainsString( 'title="GrabPay"', $result );
		$this->assertStringContainsString( 'src="/grabpay.svg"', $result );
	}

	public function test_show_lpm_payment_method_name_with_dark_icon() {
		$gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$gateway->method( 'get_account_country' )->willReturn( 'NL' );

		$payment_method = $this->createMock( UPE_Payment_Method::class );
		$payment_method->method( 'get_title' )->willReturn( 'iDEAL' );
		$payment_method->method( 'get_id' )->willReturn( 'ideal' );
		$payment_method->method( 'get_icon' )->willReturn( '/ideal.svg' );
		$payment_method->method( 'get_dark_icon' )->willReturn( '/ideal-dark.svg' );

		$result = $this->payments_order_success_page->show_lpm_payment_method_name( $gateway, $payment_method );

		$this->assertStringContainsString( 'src="/ideal.svg"', $result );
		$this->assertStringContainsString( 'data-dark-src="/ideal-dark.svg"', $result );
	}

	public function test_show_lpm_payment_method_name_icon_not_found() {
		$gateway = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$gateway->method( 'get_account_country' )->willReturn( 'SG' );

		$payment_method = $this->createMock( UPE_Payment_Method::class );
		$payment_method->method( 'get_title' )->willReturn( 'GrabPay' );
		$payment_method->method( 'get_id' )->willReturn( 'grabpay' );
		$payment_method->method( 'get_icon' )->willReturn( '' );
		$payment_method->method( 'get_dark_icon' )->willReturn( '' );

		$result = $this->payments_order_success_page->show_lpm_payment_method_name( $gateway, $payment_method, true );

		$this->assertFalse( $result );
	}

	public function test_replace_order_received_text_for_failed_orders_with_failed_status() {
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'failed' );
		$order->set_payment_method( 'woocommerce_payments' );
		$order->set_total( 50 ); // Ensure order needs payment.
		$order->save();

		// Set up global wp query vars and valid order key.
		global $wp;
		$wp->query_vars['order-received'] = $order->get_id();
		$_GET['key']                      = $order->get_order_key();

		$original_text = 'Thank you. Your order has been received.';
		$result        = $this->payments_order_success_page->replace_order_received_text_for_failed_orders( $original_text );

		$this->assertStringContainsString( 'Unfortunately, your order has failed', $result );
		$this->assertStringContainsString( wc_get_checkout_url(), $result );
	}

	public function test_replace_order_received_text_for_failed_orders_with_redirect_payment_failed_intent() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'woocommerce_payments_wechat_pay' );
		$order->set_total( 50 ); // Ensure order needs payment.
		$order->add_meta_data( '_intent_id', 'pi_123' );
		$order->save();

		// Set up global wp query vars and valid order key.
		global $wp;
		$wp->query_vars['order-received'] = $order->get_id();
		$_GET['key']                      = $order->get_order_key();

		// Mock the Get_Intention request.
		$mock_intent = WC_Helper_Intention::create_intention(
			[
				'id'                 => 'pi_123',
				'status'             => 'requires_payment_method',
				'last_payment_error' => [ 'message' => 'Payment failed' ],
			]
		);

		$this->mock_wcpay_request( Get_Intention::class, 1, 'pi_123' )
			->expects( $this->once() )
			->method( 'format_response' )
			->willReturn( $mock_intent );

		$original_text = 'Thank you. Your order has been received.';
		$result        = $this->payments_order_success_page->replace_order_received_text_for_failed_orders( $original_text );

		$this->assertStringContainsString( 'Unfortunately, your order has failed', $result );
		$this->assertStringContainsString( wc_get_checkout_url(), $result );
	}

	public function test_replace_order_received_text_for_non_failed_order() {
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'processing' );
		$order->save();

		// Set up global wp query vars and valid order key.
		global $wp;
		$wp->query_vars['order-received'] = $order->get_id();
		$_GET['key']                      = $order->get_order_key();

		$original_text = 'Thank you. Your order has been received.';
		$result        = $this->payments_order_success_page->replace_order_received_text_for_failed_orders( $original_text );

		$this->assertEquals( $original_text, $result );
	}

	public function test_replace_order_received_text_for_invalid_order() {
		// Set up global wp query vars with invalid order ID.
		global $wp;
		$wp->query_vars['order-received'] = 999999;

		$original_text = 'Thank you. Your order has been received.';
		$result        = $this->payments_order_success_page->replace_order_received_text_for_failed_orders( $original_text );

		$this->assertEquals( $original_text, $result );
	}

	public function test_replace_order_received_text_for_failed_orders_with_invalid_order_key() {
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'failed' );
		$order->set_payment_method( 'woocommerce_payments' );
		$order->set_total( 50 );
		$order->save();

		// Set up global wp query vars with invalid order key.
		global $wp;
		$wp->query_vars['order-received'] = $order->get_id();
		$_GET['key']                      = 'wc_order_INVALID';

		$original_text = 'Thank you. Your order has been received.';
		$result        = $this->payments_order_success_page->replace_order_received_text_for_failed_orders( $original_text );

		$this->assertEquals( $original_text, $result );
	}

	public function test_replace_order_received_text_for_failed_orders_with_missing_order_key() {
		$order = WC_Helper_Order::create_order();
		$order->set_status( 'failed' );
		$order->set_payment_method( 'woocommerce_payments' );
		$order->set_total( 50 );
		$order->save();

		// Set up global wp query vars without order key.
		global $wp;
		$wp->query_vars['order-received'] = $order->get_id();
		unset( $_GET['key'] );

		$original_text = 'Thank you. Your order has been received.';
		$result        = $this->payments_order_success_page->replace_order_received_text_for_failed_orders( $original_text );

		$this->assertEquals( $original_text, $result );
	}

	public function test_show_express_checkout_payment_method_name_amazon_pay_with_last4() {
		$order = WC_Helper_Order::create_order();
		$order->add_meta_data( 'last4', '4242' );
		$order->set_payment_method( 'woocommerce_payments_amazon_pay' );
		$order->save();

		$payment_method = $this->createMock( \WCPay\Payment_Methods\UPE_Payment_Method::class );
		$payment_method->method( 'get_title' )->willReturn( 'Amazon Pay' );
		$payment_method->method( 'get_icon' )->willReturn( 'amazon-pay.svg' );
		$payment_method->method( 'get_dark_icon' )->willReturn( 'amazon-pay.svg' );

		$original_map = $this->get_payment_method_map();
		$this->set_payment_method_map( array_merge( $original_map, [ 'amazon_pay' => $payment_method ] ) );

		$result = $this->payments_order_success_page->show_express_checkout_payment_method_name( $order, 'amazon_pay' );

		$this->set_payment_method_map( $original_map );

		$this->assertStringContainsString( 'wc-payment-gateway-method-logo-wrapper', $result );
		$this->assertStringContainsString( 'amazon', strtolower( $result ) );
		$this->assertStringContainsString( '•••', $result );
		$this->assertStringContainsString( '4242', $result );
	}

	public function test_show_express_checkout_payment_method_name_amazon_pay_without_last4() {
		$order = WC_Helper_Order::create_order();
		$order->set_payment_method( 'woocommerce_payments_amazon_pay' );
		$order->save();

		$payment_method = $this->createMock( \WCPay\Payment_Methods\UPE_Payment_Method::class );
		$payment_method->method( 'get_title' )->willReturn( 'Amazon Pay' );
		$payment_method->method( 'get_icon' )->willReturn( 'amazon-pay.svg' );
		$payment_method->method( 'get_dark_icon' )->willReturn( 'amazon-pay.svg' );

		$original_map = $this->get_payment_method_map();
		$this->set_payment_method_map( array_merge( $original_map, [ 'amazon_pay' => $payment_method ] ) );

		$result = $this->payments_order_success_page->show_express_checkout_payment_method_name( $order, 'amazon_pay' );

		$this->set_payment_method_map( $original_map );

		$this->assertStringContainsString( 'wc-payment-gateway-method-logo-wrapper', $result );
		$this->assertStringNotContainsString( '•••', $result );
	}
}
