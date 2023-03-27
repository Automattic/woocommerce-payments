<?php
/**
 * Experiments regarding order payment objects.
 *
 * @package WooCommerce\Payments
 */

use WCPay\Payment_Process\Order_Payment_Factory;
use WCPay\Payment_Process\Storage\Filesystem_Order_Storage;

/**
 * Temporary loader.
 *
 * @param string $class_name The name of the class.
 */
function wcpay_load_payment_process_class( $class_name ) {
	if ( 0 !== strpos( $class_name, 'WCPay\\Payment_Process\\' ) ) {
		return;
	}

	$class_name = strtolower( str_replace( '_', '-', $class_name ) );
	$parts      = explode( '\\', $class_name );
	array_shift( $parts ); // Remove WCPay.
	array_shift( $parts ); // Remove Payment_Process.
	$last     = array_pop( $parts );
	$template = __DIR__ . '/' . implode( '/', $parts ) . '/%s-' . $last . '.php';

	foreach ( [ 'class', 'interface', 'trait' ] as $type ) {
		$path = sprintf( $template, $type );
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}
spl_autoload_register( 'wcpay_load_payment_process_class' );
