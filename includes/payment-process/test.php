<?php
use WCPay\Payment_Process\Order_Payment_Factory;
use WCPay\Payment_Process\Storage\Filesystem_Storage;

spl_autoload_register(
	function( $class_name ) {
		if ( 0 === strpos( $class_name, 'WCPay\\Payment_Process\\' ) ) {
			$class_name = strtolower( str_replace( '_', '-', $class_name ) );
			$parts = explode( '\\', $class_name );
			array_shift( $parts ); // Remove WCPay.
			array_shift( $parts ); // Remove Payment_Process.
			$last = array_pop( $parts );

			require_once __DIR__ . '/' . implode( '/', $parts ) . '/' . 'class-' . $last . '.php';
		}
	}
);



$storage = new Filesystem_Storage();
$factory = new Order_Payment_Factory( $storage );

$order = wc_get_order( 1092 );
$payment = $factory->load_or_create_order_payment( $order );
$payment->save();
exit;
