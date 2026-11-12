<?php
/**
 * Demo mode: WooPayments looks connected and reads everything from fixture files.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

// Never stand in for a real connection: a site with a Jetpack blog token keeps talking to the server.
$wcpay_playground_jetpack_options = get_option( 'jetpack_private_options', [] );
if ( is_array( $wcpay_playground_jetpack_options ) && ! empty( $wcpay_playground_jetpack_options['blog_token'] ) ) {
	return;
}
unset( $wcpay_playground_jetpack_options );

add_filter(
	'wc_payments_http',
	function () {
		// Loaded here because WooPayments requires the interface right before applying this filter.
		require_once __DIR__ . '/class-wcpay-playground-demo-http.php';

		return new WCPay_Playground_Demo_Http( __DIR__ . '/fixtures' );
	}
);

// The fixture account is a sandbox one; this keeps the plugin in test mode and shows its own banner about it.
add_filter( 'wcpay_dev_mode', '__return_true' );

add_action(
	'admin_bar_menu',
	function ( $admin_bar ) {
		$admin_bar->add_node(
			[
				'id'    => 'wcpay-playground-preview',
				'title' => 'WooPayments: demo data',
				'meta'  => [
					'title' => 'This store is not connected to WooPayments. Balances, payouts, transactions and disputes come from fixture files, and payments cannot be taken.',
				],
			]
		);
	},
	100
);
