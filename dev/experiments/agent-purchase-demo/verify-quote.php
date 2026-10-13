<?php
/** Verify real local WooCommerce quote totals and shopper state isolation. */
$quotes = wcpay_get_container()->get( \WCPay\Internal\Service\AgentPurchases\QuoteService::class );
$product_id = (int) getenv( 'AGENT_DEMO_PRODUCT_ID' );
$address = array( 'first_name' => 'Demo', 'last_name' => 'Shopper', 'address_1' => '123 Demo Street', 'address_2' => '', 'city' => 'San Francisco', 'state' => 'CA', 'postcode' => '94107', 'country' => 'US' );
WC()->session = new class() extends WC_Session {};
WC()->session->set( 'agent_demo_isolation_sentinel', 'preserve' );
WC()->customer = new WC_Customer( 0 );
WC()->cart = new WC_Cart();
$original_cart_contents = WC()->cart->get_cart_contents();
$original_session_data = WC()->session->get( 'agent_demo_isolation_sentinel' );
$original = array( WC()->cart, WC()->customer, WC()->session, WC()->shipping()->get_packages() );
$quote = $quotes->calculate( $product_id, 2, $address );
if ( $original !== array( WC()->cart, WC()->customer, WC()->session, WC()->shipping()->get_packages() ) || $original_cart_contents !== WC()->cart->get_cart_contents() || $original_session_data !== WC()->session->get( 'agent_demo_isolation_sentinel' ) ) {
	throw new RuntimeException( 'FAIL: shopper state changed: ' . wp_json_encode( array( 'cart' => $original[0] === WC()->cart, 'customer' => $original[1] === WC()->customer, 'session' => $original[2] === WC()->session, 'shipping' => $original[3] === WC()->shipping()->get_packages(), 'contents' => $original_cart_contents === WC()->cart->get_cart_contents(), 'sentinel' => $original_session_data === WC()->session->get( 'agent_demo_isolation_sentinel' ) ) ) );
}
if ( (float) $quote['shipping_total'] <= 0 || (float) $quote['tax_total'] <= 0 || abs( (float) $quote['total'] - (float) $quote['subtotal'] - (float) $quote['shipping_total'] - (float) $quote['tax_total'] ) > 0.001 ) {
	throw new RuntimeException( 'FAIL: expected positive shipping/tax and balanced quote.' );
}
foreach ( array( 0, 6 ) as $quantity ) {
	try {
		$quotes->calculate( $product_id, $quantity, $address );
		throw new LogicException( 'FAIL: invalid quantity accepted.' );
	} catch ( RuntimeException $e ) {}
}
$bad_address = $address;
$bad_address['country'] = 'GB';
try {
	$quotes->calculate( $product_id, 1, $bad_address );
	throw new LogicException( 'FAIL: invalid address accepted.' );
} catch ( RuntimeException $e ) {}
try {
	$quotes->calculate( 0, 1, $address );
	throw new LogicException( 'FAIL: invalid product accepted.' );
} catch ( RuntimeException $e ) {}
$product = wc_get_product( $product_id );
$old_price = $product->get_regular_price();
try {
	$product->set_regular_price( '13.00' );
	$product->set_price( '13.00' );
	$product->save();
	try {
		$quotes->make_order( $quote, 0 );
		throw new LogicException( 'FAIL: changed product price accepted.' );
	} catch ( RuntimeException $e ) {}
} finally {
	$product->set_regular_price( $old_price );
	$product->set_price( $old_price );
	$product->save();
}
$changed = $quote;
$changed['fingerprint'] = 'changed';
try {
	$quotes->make_order( $changed, 0 );
	throw new LogicException( 'FAIL: modified quote accepted.' );
} catch ( RuntimeException $e ) {}
$original = array( WC()->cart, WC()->customer, WC()->session, WC()->shipping()->get_packages() );
$original_session_data = WC()->session->get( 'agent_demo_isolation_sentinel' );
$order = $quotes->make_order( $quote, 0 );
if ( $original !== array( WC()->cart, WC()->customer, WC()->session, WC()->shipping()->get_packages() ) || $original_session_data !== WC()->session->get( 'agent_demo_isolation_sentinel' ) ) {
	$order->delete( true );
	throw new RuntimeException( 'FAIL: order creation changed shopper state.' );
}
try {
	if ( $order->get_total() !== $quote['total'] || count( $order->get_items() ) !== 1 || count( $order->get_items( 'shipping' ) ) !== 1 || count( $order->get_items( 'tax' ) ) < 1 || $order->get_created_via() !== 'wcpay-agent-purchase' ) {
		throw new RuntimeException( 'FAIL: order does not preserve quoted real line items/totals.' );
	}
	echo wp_json_encode( array( 'result' => 'PASS', 'quote' => $quote, 'order_id' => $order->get_id() ), JSON_PRETTY_PRINT ) . "\n";
} finally {
	$order->delete( true );
}
