<?php
function wcpay_core_loader( $class )
{
	if (strpos($class, 'WCPay\Core') !== 0) {
		return;
	}
	$class_parts = array_slice(explode('\\', $class), 2);
	$class_name = array_pop($class_parts);
	$class_parts[] = 'class-' . _wp_to_kebab_case( $class_name ) . ".php";


	$file = __DIR__ . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $class_parts);
	if (file_exists($file)) {
		require_once ($file);
	}
}
