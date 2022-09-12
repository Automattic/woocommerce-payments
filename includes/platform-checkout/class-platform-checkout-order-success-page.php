<?php
/**
 * Class Platform_Checkout_Order_Success_Page
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Platform_Checkout;

defined( 'ABSPATH' ) || exit;

use WC_Payment_Gateway_WCPay;

/**
 * Handling for when customers come back to the merchant store after platform checkout.
 */
class Platform_Checkout_Order_Success_Page {
	/**
	 * Internal ID of the payment gateway.
	 *
	 * @type string
	 */
	const GATEWAY_ID = 'woopay';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'wp', [ $this, 'maybe_process_platform_checkout_redirect' ] );
	}

	/**
	 * Doc comment.
	 *
	 * @return void
	 */
	public function maybe_process_platform_checkout_redirect() {
		if ( ! is_order_received_page() ) {
			return;
		}

		$payment_method = isset( $_GET['wc_payment_method'] ) ? wc_clean( wp_unslash( $_GET['wc_payment_method'] ) ) : '';
		if ( self::GATEWAY_ID !== $payment_method ) {
			return;
		}

		$order_id    = isset( $_GET['order_id'] ) ? wc_clean( wp_unslash( $_GET['order_id'] ) ) : '';
		$last_4      = isset( $_GET['last4'] ) ? wc_clean( wp_unslash( $_GET['last4'] ) ) : '';
		$from_woopay = isset( $_GET['from_woopay'] ) ? filter_var( wc_clean( wp_unslash( $_GET['from_woopay'] ) ), FILTER_VALIDATE_BOOL ) : false;

		if ( empty( $order_id ) || $order_id <= 0 ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || ! is_object( $order ) ) {
			return;
		}

		if ( $order->has_status( [ 'processing', 'completed', 'on-hold' ] ) ) {
			return;
		}

		if ( ! $from_woopay ) {
			return;
		}

		$order->set_payment_method( WC_Payment_Gateway_WCPay::GATEWAY_ID );
		$order->add_meta_data( 'is_woopay', true, true );
		$order->add_meta_data( 'last4', $last_4, true );
		$order->set_status( 'pending' );
		$order->save();
	}
}
