<?php
/**
 * Experiments regarding order payment objects.
 *
 * @package WooCommerce\Payments
 */

use WCPay\Payment\Process\Process;
use WCPay\Payment\State\Verified_State;
use WCPay\Payment\Strategy\Setup_Payment_Strategy;
use WCPay\Payment\Strategy\Standard_Payment_Strategy;
use WCPay\Payment_Process\Payment_Method\New_Payment_Method;

/**
 * Temporary loader.
 *
 * @param string $class_name The name of the class.
 */
function wcpay_load_Payment_class( $class_name ) {
	if ( 0 !== strpos( $class_name, 'WCPay\\Payment\\' ) ) {
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
spl_autoload_register( 'wcpay_load_Payment_class' );



function wcpay_payment() {
	$order = wc_get_order( 1314 );
	$order->set_total( 12 );

	$payment = new WCPay\Payment\Payment();
	$payment->set_payment_method( new New_Payment_Method( 'pm_1MrM1nQum4wPblDPPeQJZgun' ) );
	$payment->set_order( $order );
	// $payment->set_fraud_prevention_token( 'dasdsa' );
	$payment->set_flag( WCPay\Payment\Flags::RECURRING );
	$payment->prepare();
	$payment->verify();

	$strategy = $order->get_total() > 0 ? new Standard_Payment_Strategy() : new Setup_Payment_Strategy();
	var_dump( $payment );
	$payment->process( $strategy );
	$payment->complete();

	var_dump( $payment ); exit;
}

add_action( 'template_redirect', function() {
	if ( isset( $_GET['rado'] ) ) {
		wcpay_payment();
	}
} );
