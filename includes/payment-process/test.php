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
	$last = array_pop( $parts );

	require_once __DIR__ . '/' . implode( '/', $parts ) . '/class-' . $last . '.php';
}
spl_autoload_register( 'wcpay_load_payment_process_class' );

$storage = new Filesystem_Order_Storage();
$factory = new Order_Payment_Factory( $storage );

$the_order = wc_get_order( 1092 );
$payment   = $factory->load_or_create_order_payment( $the_order );
