<?php

// Some of the packages register autoloaders, so let's load what we can from these.
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/autoload_packages.php';

// WordPress
define( 'WP_DEBUG', true );

define( 'WPINC', 'wp-includes' );
define( 'ABSPATH', __DIR__ . '/vendor/wordpress/wordpress/');

define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );

$GLOBALS['wp_embed'] = false;

require_once ABSPATH . 'wp-includes/functions.php';
require_once ABSPATH . 'wp-includes/functions.wp-scripts.php';
require_once ABSPATH . 'wp-includes/plugin.php';
require_once ABSPATH . 'wp-includes/functions.wp-styles.php';
require_once ABSPATH . 'wp-includes/formatting.php';
require_once ABSPATH . 'wp-includes/load.php';
require_once ABSPATH . 'wp-includes/cache.php';
require_once ABSPATH . 'wp-includes/l10n.php';
require_once ABSPATH . 'wp-includes/capabilities.php';
require_once ABSPATH . 'wp-includes/link-template.php';
require_once ABSPATH . 'wp-includes/kses.php';
require_once ABSPATH . 'wp-includes/pluggable.php';
require_once ABSPATH . 'wp-includes/general-template.php';
require_once ABSPATH . 'wp-includes/http.php';

require_once ABSPATH . 'wp-includes/class-wp-error.php';
require_once ABSPATH . 'wp-includes/class-wp-widget.php';
require_once ABSPATH . 'wp-includes/class-wp-user.php';

require_once ABSPATH . 'wp-admin/includes/screen.php';

// WordPress REST API
require_once ABSPATH . 'wp-includes/class-wp-http-response.php';
require_once ABSPATH . 'wp-includes/rest-api/endpoints/class-wp-rest-controller.php';
require_once ABSPATH . 'wp-includes/rest-api/class-wp-rest-server.php';
require_once ABSPATH . 'wp-includes/rest-api/class-wp-rest-request.php';
require_once ABSPATH . 'wp-includes/rest-api/class-wp-rest-response.php';
require_once ABSPATH . 'wp-includes/rest-api.php';

// WP Admin
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';

// WooCommerce Admin
require_once __DIR__ . '/vendor/woocommerce/woocommerce-admin/includes/page-controller-functions.php';

// WooCommerce
define( 'WC_PLUGIN_FILE', __DIR__ . '/vendor/woocommerce/woocommerce/woocommerce.php' );
define( 'WC_ABSPATH', __DIR__ . '/vendor/woocommerce/woocommerce/' );

require_once __DIR__ . '/vendor/woocommerce/woocommerce/includes/interfaces/class-wc-logger-interface.php';
require_once __DIR__ . '/vendor/woocommerce/woocommerce/includes/class-wc-logger.php';

require_once __DIR__ . '/vendor/woocommerce/woocommerce/includes/abstracts/abstract-wc-settings-api.php';
require_once __DIR__ . '/vendor/woocommerce/woocommerce/includes/abstracts/abstract-wc-payment-gateway.php';
require_once __DIR__ . '/vendor/woocommerce/woocommerce/includes/gateways/class-wc-payment-gateway-cc.php';

require_once __DIR__ . '/vendor/woocommerce/woocommerce/includes/traits/trait-wc-item-totals.php';
require_once __DIR__ . '/vendor/woocommerce/woocommerce/includes/abstracts/abstract-wc-data.php';
require_once __DIR__ . '/vendor/woocommerce/woocommerce/includes/legacy/abstract-wc-legacy-order.php';
require_once __DIR__ . '/vendor/woocommerce/woocommerce/includes/abstracts/abstract-wc-order.php';
require_once __DIR__ . '/vendor/woocommerce/woocommerce/includes/class-wc-order.php';
require_once __DIR__ . '/vendor/woocommerce/woocommerce/includes/class-wc-order-refund.php';

require_once __DIR__ . '/vendor/woocommerce/woocommerce/includes/wc-core-functions.php';
require_once __DIR__ . '/vendor/woocommerce/woocommerce/includes/wc-notice-functions.php';

function WC() {
	return;
}

// Jetpack
define( 'JETPACK_MASTER_USER', 1 );
define( 'JETPACK__PLUGIN_DIR', __DIR__ . '/docker/wordpress/wp-content/plugins/jetpack/' );
define( 'JETPACK__SANDBOX_DOMAIN', false );

require_once __DIR__ . '/vendor/automattic/jetpack/class.jetpack-data.php';

// WooCommerce Payments
define( 'WCPAY_VERSION_NUMBER', '0.0' );

require_once dirname( __FILE__ ) . '/woocommerce-payments.php';

require_once dirname( __FILE__ ) . '/includes/wc-payment-api/models/class-wc-payments-api-charge.php';
require_once dirname( __FILE__ ) . '/includes/wc-payment-api/models/class-wc-payments-api-intention.php';
require_once dirname( __FILE__ ) . '/includes/wc-payment-api/class-wc-payments-api-client.php';
require_once dirname( __FILE__ ) . '/includes/wc-payment-api/class-wc-payments-http.php';
