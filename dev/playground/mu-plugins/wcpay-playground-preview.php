<?php
/**
 * Plugin Name: WooPayments Playground preview
 * Description: Lets WooPayments run in a WordPress Playground preview of a pull request on fixture data, without a connection.
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

// Opt-in only: the preview Blueprint defines this constant. Without it, or with any other value, this file does nothing.
$wcpay_playground_preview_mode = defined( 'WCPAY_PLAYGROUND_PREVIEW' ) ? WCPAY_PLAYGROUND_PREVIEW : '';

if ( 'demo' === $wcpay_playground_preview_mode ) {
	require __DIR__ . '/wcpay-playground-preview/demo.php';
}

unset( $wcpay_playground_preview_mode );
