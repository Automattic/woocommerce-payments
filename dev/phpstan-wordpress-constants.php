<?php
/**
 * Custom WordPress constants stub file for PHPStan
 * This file provides definitions for common WordPress constants that are missing from the official stubs
 */

// Time constants
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );
define( 'WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS );
define( 'MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS );
define( 'YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS );

// Common WordPress constants
define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
define( 'WP_CONTENT_URL', '/wp-content' );
define( 'WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins' );
define( 'WP_ADMIN', true );
define( 'WP_NETWORK_ADMIN', false );
define( 'WP_USER_ADMIN', false );
define( 'DOING_AJAX', false );
define( 'DOING_CRON', false );
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', false );
define( 'SCRIPT_DEBUG', false );
define( 'WP_CACHE', false );
define( 'COOKIEHASH', 'default' );
define( 'COOKIEPATH', '/' );
define( 'SITECOOKIEPATH', '/' );
define( 'ADMIN_COOKIE_PATH', '/wp-admin' );
define( 'PLUGINS_COOKIE_PATH', '/wp-content/plugins' );
define( 'TEMPLATEPATH', '/wp-content/themes' );
define( 'STYLESHEETPATH', '/wp-content/themes' );

// Database constants
define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// WP Filesystem
define( 'FS_METHOD', 'direct' );
define( 'FS_CHMOD_DIR', 0755 );
define( 'FS_CHMOD_FILE', 0644 );

// Multisite constants
define( 'WP_ALLOW_MULTISITE', false );
define( 'MULTISITE', false );
define( 'SUBDOMAIN_INSTALL', false );
define( 'DOMAIN_CURRENT_SITE', 'example.com' );
define( 'PATH_CURRENT_SITE', '/' );
define( 'SITE_ID_CURRENT_SITE', 1 );
define( 'BLOG_ID_CURRENT_SITE', 1 );

// Common plugin constants
define( 'WOOCOMMERCE_VERSION', '8.0.0' );
define( 'WC_VERSION', '8.0.0' );
define( 'WC_PLUGIN_FILE', __DIR__ . '/woocommerce/woocommerce.php' );
define( 'WC_ABSPATH', __DIR__ . '/woocommerce/' );
define( 'WC_LOG_DIR', __DIR__ . '/wc-logs/' );

// WooCommerce Payments constants
define( 'WCPAY_ABSPATH', __DIR__ . '/woocommerce-payments/' );
define( 'WCPAY_PLUGIN_FILE', __DIR__ . '/woocommerce-payments/woocommerce-payments.php' );
define( 'WCPAY_VERSION', '1.0.0' );
