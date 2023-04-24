<?php
/**
 * Trait WC_Payments_Subscriptions_Utilities
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Utility functions related to WC Subscriptions.
 */
trait WC_Payments_Subscriptions_Utilities {

	/**
	 * Checks if subscriptions are enabled on the site.
	 *
	 * Subscriptions functionality is enabled if the WC Subscriptions plugin is active and greater than v 2.2, or the base feature is turned on.
	 *
	 * @return bool Whether subscriptions is enabled or not.
	 */
	public function is_subscriptions_enabled() {
		if ( $this->is_subscriptions_plugin_active() ) {
			return version_compare( $this->get_subscriptions_plugin_version(), '2.2.0', '>=' );
		}

		// TODO update this once we know how the base library feature will be enabled.
		return class_exists( 'WC_Subscriptions_Core_Plugin' );
	}

	/**
	 * Returns whether this user is changing the payment method for a subscription.
	 *
	 * @return bool
	 */
	public function is_changing_payment_method_for_subscription() {
		if ( isset( $_GET['change_payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return wcs_is_subscription( wc_clean( wp_unslash( $_GET['change_payment_method'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		return false;
	}

	/**
	 * Returns boolean value indicating whether payment for an order will be recurring,
	 * as opposed to single.
	 *
	 * @param int $order_id ID for corresponding WC_Order in process.
	 *
	 * @return bool
	 */
	public function is_payment_recurring( $order_id ) {
		if ( ! $this->is_subscriptions_enabled() ) {
			return false;
		}
		return $this->is_changing_payment_method_for_subscription() || wcs_order_contains_subscription( $order_id );
	}

	/**
	 * Returns a boolean value indicating whether the save payment checkbox should be
	 * displayed during checkout.
	 *
	 * Returns `false` if the cart currently has a subscriptions or if the request has a
	 * `change_payment_method` GET parameter. Returns the value in `$display` otherwise.
	 *
	 * @param bool $display Bool indicating whether to show the save payment checkbox in the absence of subscriptions.
	 *
	 * @return bool Indicates whether the save payment method checkbox should be displayed or not.
	 */
	public function display_save_payment_method_checkbox( $display ) {
		if ( WC_Subscriptions_Cart::cart_contains_subscription() || $this->is_changing_payment_method_for_subscription() ) {
			return false;
		}
		// Only render the "Save payment method" checkbox if there are no subscription products in the cart.
		return $display;
	}

	/**
	 * Returns boolean on whether current WC_Cart or WC_Subscriptions_Cart
	 * contains a subscription or subscription renewal item
	 *
	 * @return bool
	 */
	public function is_subscription_item_in_cart() {
		if ( $this->is_subscriptions_enabled() ) {
			return WC_Subscriptions_Cart::cart_contains_subscription() || $this->cart_contains_renewal();
		}
		return false;
	}

	/**
	 * Checks the cart to see if it contains a subscription product renewal.
	 *
	 * @return mixed The cart item containing the renewal as an array, else false.
	 */
	public function cart_contains_renewal() {
		if ( ! function_exists( 'wcs_cart_contains_renewal' ) ) {
			return false;
		}
		return wcs_cart_contains_renewal();
	}

	/**
	 * Checks if the WC Subscriptions plugin is active.
	 *
	 * @return bool Whether the plugin is active or not.
	 */
	public function is_subscriptions_plugin_active() {
		return class_exists( 'WC_Subscriptions' );
	}

	/**
	 * Gets the version of WooCommerce Subscriptions that is active.
	 *
	 * @return null|string The plugin version. Returns null when WC Subscriptions is not active/loaded.
	 */
	public function get_subscriptions_plugin_version() {
		return class_exists( 'WC_Subscriptions' ) ? WC_Subscriptions::$version : null;
	}

	/**
	 * Gets the version of the subscriptions-core library.
	 *
	 * @return null|string The version number of subscriptions-core or null if not active.
	 */
	public function get_subscriptions_core_version() {
		$subscriptions_core_instance = WC_Subscriptions_Core_Plugin::instance();

		// For backwards compatibility with older versions of WC Subscriptions, we need to do an existence check.
		if ( method_exists( $subscriptions_core_instance, 'get_library_version' ) ) {
			return $subscriptions_core_instance->get_library_version();
		}
		return $subscriptions_core_instance ? $subscriptions_core_instance->get_plugin_version() : null;
	}
}
