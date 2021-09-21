<?php
// phpcs:ignoreFile - This is related to the Psalm static code analysis, and not part of the plugin.

require_once __DIR__ . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';
require_once __DIR__ . '/vendor/php-stubs/woocommerce-stubs/woocommerce-stubs.php';
require_once __DIR__ . '/vendor/php-stubs/woocommerce-stubs/woocommerce-packages-stubs.php';

require_once __DIR__ . '/vendor/autoload.php';

define( 'ABSPATH', __DIR__ );
define( 'WCPAY_ABSPATH', __DIR__ . '/' );
define( 'WCPAY_PLUGIN_FILE', WCPAY_ABSPATH . 'woocommerce-payments.php' );

require_once __DIR__ . '/includes/multi-currency/wc-payments-multi-currency.php';
