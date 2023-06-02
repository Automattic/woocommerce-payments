<?php
/**
 * Subscription WCS_Staging helper.
 *
 * @package WooCommerce\Payments\Tests
 */

/**
 * Class WC_Subscriptions_Product.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WCS_Staging {

	/**
	 * Mock of Subscriptions Core function that determines if this is a duplicate/staging site.
	 *
	 * Checks if the WordPress site URL is the same as the URL subscriptions considers
	 * the live URL (@see self::set_duplicate_site_url_lock()).
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v4.0.0
	 * @return bool Whether the site is a duplicate URL or not.
	 */
	public static function is_duplicate_site() {
		return apply_filters( 'woocommerce_subscriptions_is_duplicate_site', false );
	}
}
