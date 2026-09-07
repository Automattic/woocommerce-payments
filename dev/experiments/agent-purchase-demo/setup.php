<?php
/** Create local-only agent-demo fixtures and private runtime credentials. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) || ! class_exists( 'WC_Payments' ) || ! WC_Payments::mode()->is_test() || ! WC_Payments::mode()->is_dev() || 'development' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Setup requires the local WooPayments test-mode store.' );
}
add_filter( 'pre_wp_mail', '__return_true' );
$old_umask = umask( 0077 );
try {
	WC_Tax::create_tax_class( 'Agent demo', 'agent-demo' );
	$tax_id = (int) get_option( 'wcpay_agent_demo_tax_id' );
	if ( ! $tax_id || ! WC_Tax::_get_tax_rate( $tax_id ) ) {
		$tax_id = WC_Tax::_insert_tax_rate( array( 'tax_rate_country' => 'US', 'tax_rate_state' => 'CA', 'tax_rate' => '8.0000', 'tax_rate_name' => 'Demo tax', 'tax_rate_priority' => 1, 'tax_rate_compound' => 0, 'tax_rate_shipping' => 1, 'tax_rate_order' => 0, 'tax_rate_class' => 'agent-demo' ) );
		WC_Tax::_update_tax_rate_postcodes( $tax_id, '94107' );
		update_option( 'wcpay_agent_demo_tax_id', $tax_id, false );
	}
	$zone_id = (int) get_option( 'wcpay_agent_demo_zone_id' );
	$zone = new WC_Shipping_Zone( $zone_id );
	if ( ! $zone_id || ! $zone->get_id() ) {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'Agent demo — 94107' );
		$zone->set_zone_order( 0 );
		$zone->add_location( 'US', 'country' );
		$zone->add_location( 'US:CA', 'state' );
		$zone->add_location( '94107', 'postcode' );
		$zone->save();
		$zone_id = $zone->get_id();
		update_option( 'wcpay_agent_demo_zone_id', $zone_id, false );
	}
	$methods = $zone->get_shipping_methods();
	$method_id = 0;
	foreach ( $methods as $method ) {
		if ( 'flat_rate' === $method->id ) {
			$method_id = $method->get_instance_id();
			break;
		}
	}
	if ( ! $method_id ) {
		$method_id = $zone->add_shipping_method( 'flat_rate' );
	}
	update_option( 'woocommerce_flat_rate_' . $method_id . '_settings', array( 'title' => 'Demo delivery', 'tax_status' => 'taxable', 'cost' => '2.50' ), false );
	WC_Cache_Helper::get_transient_version( 'shipping', true );
	$product_id = (int) get_option( 'wcpay_agent_demo_product_id' );
	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Agent demo coffee — 250g' );
		$product->set_description( 'A local test product for shopper-approved agent purchases. No goods will be shipped.' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '12.00' );
		$product->set_price( '12.00' );
		$product->set_virtual( false );
		$product->set_weight( '0.25' );
		$product->set_tax_status( 'taxable' );
		$product->set_tax_class( 'agent-demo' );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 20 );
		$product->set_backorders( 'no' );
		$product->update_meta_data( '_wcpay_agent_demo_product', 'yes' );
		$product_id = $product->save();
		update_option( 'wcpay_agent_demo_product_id', $product_id, false );
	} elseif ( 'yes' !== $product->get_meta( '_wcpay_agent_demo_product' ) ) {
		throw new RuntimeException( 'Configured product is not a demo fixture.' );
	}
	$username = 'agent_demo_shopper';
	$shopper_id = username_exists( $username );
	$shopper_path = '/tmp/wcpay-agent-demo-shopper.json';
	if ( $shopper_id && 'yes' !== get_user_meta( $shopper_id, '_wcpay_agent_demo', true ) ) {
		throw new RuntimeException( 'Existing shopper username is not a demo fixture.' );
	}
	if ( ! $shopper_id || ! file_exists( $shopper_path ) ) {
		$password = wp_generate_password( 32, true, true );
		if ( ! $shopper_id ) {
			$shopper_id = wp_insert_user( array( 'user_login' => $username, 'user_pass' => $password, 'user_email' => 'agent-demo-shopper@example.test', 'role' => 'customer', 'first_name' => 'Demo', 'last_name' => 'Shopper' ) );
			if ( is_wp_error( $shopper_id ) ) {
				throw new RuntimeException( 'Could not create the demo shopper.' );
			}
			update_user_meta( $shopper_id, '_wcpay_agent_demo', 'yes' );
		} else {
			wp_set_password( $password, $shopper_id );
		}
		file_put_contents( $shopper_path, wp_json_encode( array( 'username' => $username, 'password' => $password ) ) );
		chmod( $shopper_path, 0600 );
	}
	update_option( 'wcpay_agent_purchase_product_ids', array( $product_id ), false );
	update_option( 'wcpay_agent_purchase_shopper_id', $shopper_id, false );
	$agent_path = '/tmp/wcpay-agent-demo-agent.json';
	$config = file_exists( $agent_path ) ? json_decode( file_get_contents( $agent_path ), true ) : null;
	if ( ! is_array( $config ) || empty( $config['key'] ) ) {
		$config = array( 'baseUrl' => 'http://localhost:8082', 'key' => bin2hex( random_bytes( 32 ) ) );
		file_put_contents( $agent_path, wp_json_encode( $config ) );
		chmod( $agent_path, 0600 );
	}
	update_option( 'wcpay_agent_purchase_key_hash', hash( 'sha256', $config['key'] ), false );
	update_option( 'wcpay_agent_purchases_experiment_enabled', 'yes', false );
	foreach ( array( 'wcpay_agent_purchases_enabled' => 'yes', 'wcpay_agent_purchases_web_enabled' => 'yes' ) as $option => $value ) {
		if ( false === get_option( $option, false ) ) {
			add_option( $option, $value, '', false );
		}
	}
	echo wp_json_encode( array( 'product_id' => $product_id, 'shopper_id' => $shopper_id, 'shipping_zone_id' => $zone_id, 'tax_rate_id' => $tax_id ) ) . "\n";
} finally {
	umask( $old_umask );
}
