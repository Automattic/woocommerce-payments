<?php
/**
 * Experiments regarding payment objects.
 *
 * @package WooCommerce\Payments
 */

/**
 * Temporary loader.
 *
 * @param string $class_name The name of the class.
 */
function wcpay_load_payment_class( $class_name ) {
	if ( 0 !== strpos( $class_name, 'WCPay\\Payment\\' ) ) {
		return;
	}

	$class_name = strtolower( str_replace( '_', '-', $class_name ) );
	$parts      = explode( '\\', $class_name );
	array_shift( $parts ); // Remove WCPay.
	array_shift( $parts ); // Remove Payment.
	$last     = array_pop( $parts );
	$template = __DIR__ . '/' . implode( '/', $parts ) . '/%s-' . $last . '.php';

	foreach ( [ 'class', 'interface', 'trait' ] as $type ) {
		$path = sprintf( $template, $type );
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}
spl_autoload_register( 'wcpay_load_payment_class' );
