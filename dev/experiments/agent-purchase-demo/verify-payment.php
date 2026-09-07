<?php
/** Verify the paid local fixture, channel independence, and refund the test charge. */
WCPay_Agent_Demo::guard();
$order = wc_get_order( (int) ( $args[0] ?? 0 ) );
if ( ! $order || 'yes' !== $order->get_meta( '_wcpay_agent_demo' ) || ! $order->is_paid() || ! $order->get_transaction_id() || 'test' !== $order->get_meta( '_wcpay_mode' ) ) { throw new RuntimeException( 'Expected a paid demo test order.' ); }
if ( '15.66' !== $order->get_total() || count( $order->get_items() ) !== 1 || count( $order->get_items( 'shipping' ) ) !== 1 || count( $order->get_items( 'tax' ) ) !== 1 ) { throw new RuntimeException( 'Order lines do not match the quote.' ); }
$original = get_option( 'wcpay_agent_demo_web_enabled', 'yes' );
try {
	update_option( 'wcpay_agent_demo_web_enabled', 'no' );
	$gateways = apply_filters( 'woocommerce_available_payment_gateways', array( 'woocommerce_payments' => WC_Payments::get_gateway(), 'bacs' => new WC_Gateway_BACS() ) );
	if ( isset( $gateways['woocommerce_payments'] ) || ! isset( $gateways['bacs'] ) || 'yes' !== get_option( 'wcpay_agent_demo_enabled' ) ) { throw new RuntimeException( 'Independent channel settings failed.' ); }
	update_option( 'wcpay_agent_demo_web_enabled', 'yes' );
	$gateways = apply_filters( 'woocommerce_available_payment_gateways', array( 'woocommerce_payments' => WC_Payments::get_gateway() ) );
	if ( ! isset( $gateways['woocommerce_payments'] ) ) { throw new RuntimeException( 'Website WooPayments setting failed.' ); }
} finally { update_option( 'wcpay_agent_demo_web_enabled', $original ); }
add_filter( 'pre_wp_mail', '__return_true' );
$refund = wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => $order->get_total(), 'reason' => 'Local agent demo verification cleanup', 'refund_payment' => true, 'restock_items' => false ) );
if ( is_wp_error( $refund ) ) { throw new RuntimeException( $refund->get_error_message() ); }
$order = wc_get_order( $order->get_id() );
if ( 'refunded' !== $order->get_status() || (float) $order->get_total_refunded() !== (float) $order->get_total() ) { throw new RuntimeException( 'Refund not confirmed.' ); }
echo wp_json_encode( array( 'order_id' => $order->get_id(), 'mode' => $order->get_meta( '_wcpay_mode' ), 'status' => $order->get_status(), 'refunded' => $order->get_total_refunded(), 'channel_settings' => 'passed' ) ) . "\n";
