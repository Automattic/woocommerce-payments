<?php
// phpcs:ignoreFile - This is related to the Psalm static code analysis, and not part of the plugin.

require_once __DIR__ . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';
require_once __DIR__ . '/vendor/php-stubs/woocommerce-stubs/woocommerce-stubs.php';
require_once __DIR__ . '/vendor/php-stubs/woocommerce-stubs/woocommerce-packages-stubs.php';

require_once __DIR__ . '/vendor/autoload.php';

define( 'ABSPATH', __DIR__ );
define( 'WCPAY_ABSPATH', __DIR__ . '/' );
define( 'WCPAY_PLUGIN_FILE', __DIR__ . '/woocommerce-payments.php' );

require_once __DIR__ . '/includes/class-wc-payments-features.php';
require_once __DIR__ . '/includes/class-wc-payments.php';

/* extract lines where files are included (order is important) */
$files = array_filter(
	file( __DIR__ . '/includes/class-wc-payments.php' ),
	static function ( string $line ): bool {
		return strpos( $line, 'include_once ' ) !== false;
	}
);
/* normalize the paths and include them */
foreach ( $files as $line ) {
	$parts = explode( ' ', trim( $line ) );
	$file  = __DIR__ . '/includes/' . trim( array_pop( $parts ), "';/");
	$file  = str_replace( '/includes/includes/', '/includes/', $file );
	require_once $file;
}
