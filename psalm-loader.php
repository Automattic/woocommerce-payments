<?php
// phpcs:ignoreFile - This is related to the Psalm static code analysis, and not part of the plugin.

require_once __DIR__ . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';
require_once __DIR__ . '/vendor/php-stubs/woocommerce-stubs/woocommerce-stubs.php';
require_once __DIR__ . '/vendor/php-stubs/woocommerce-stubs/woocommerce-packages-stubs.php';

require_once __DIR__ . '/vendor/autoload.php';

/* required in order to load the classes included later on */
define( 'ABSPATH', __DIR__ );
define( 'WCPAY_ABSPATH', __DIR__ . '/' );
define( 'WCPAY_PLUGIN_FILE', __DIR__ . '/woocommerce-payments.php' );

require_once __DIR__ . '/includes/class-wc-payments-features.php';
require_once __DIR__ . '/includes/class-wc-payments.php';

/* here we extract all inclusions and including the files in the same order as WCPay does */
foreach ( file( __DIR__ . '/includes/class-wc-payments.php' ) as $line ) {
	if ( strpos( $line, 'include_once ' ) !== false ) {
		$parts = explode( ' ', trim( $line ) );
		$file  = __DIR__ . '/includes/' . trim( array_pop( $parts ), "';/" );
		$file  = str_replace( '/includes/includes/', '/includes/', $file );
		require_once $file;
	}
}
