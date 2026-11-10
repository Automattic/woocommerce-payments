<?php
/**
 * Stub for the function that adds a route to WooCommerce's experimental settings screen.
 *
 * wp-build generates it in WooCommerce's build output, and WooCommerce only loads it while it loads
 * the screen. `SettingsScreenService` checks that it exists before calling it.
 *
 * Consumers: PHPStan (discovered via `phpstan.neon` → `scanFiles`).
 *
 * @package WooCommerce\Payments
 */

/**
 * Register a route for the wc-settings-screen-wp-admin page.
 *
 * @param string      $path           Route path (e.g., '/types/$type/edit/$id').
 * @param string|null $content_module Script module ID for content (stage/inspector).
 * @param string|null $route_module   Script module ID for route lifecycle hooks.
 */
function woocommerce_routes_register_wc_settings_screen_wp_admin_route( $path, $content_module = null, $route_module = null ) {}
