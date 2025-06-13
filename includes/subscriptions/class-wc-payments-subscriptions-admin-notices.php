<?php
/**
 * Class WC_Payments_Subscriptions_Admin_Notices
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class for handling admin notices related to Stripe Billing deprecation.
 */
class WC_Payments_Subscriptions_Admin_Notices {
	/**
	 * Initializes this class's WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'admin_notices', [ $this, 'display_stripe_billing_deprecation_notice' ] );
	}

	/**
	 * Display admin notice for Stripe Billing deprecation.
	 */
	public function display_stripe_billing_deprecation_notice() {
		// Only show on subscription-related pages.
		if ( ! $this->is_subscription_page() ) {
			return;
		}

		// Only show if Stripe Billing is enabled and WooCommerce Subscriptions is not active.
		if ( ! WC_Payments_Features::is_stripe_billing_enabled() || class_exists( 'WC_Subscriptions' ) ) {
			return;
		}

		$wcpay_version = WC_Payments::get_file_version( WCPAY_PLUGIN_FILE );
		$message       = '';

		if ( version_compare( $wcpay_version, '9.7.0', '<' ) ) {
			$message = sprintf(
				/* translators: %1$s: WooCommerce Subscriptions */
				__( '<strong>Important:</strong> From version 9.7 of WooPayments (scheduled for 23 July, 2025), you\'ll <strong>no longer be able to offer new product subscriptions</strong>. To avoid disruption, please install <a target="_blank" href="%1$s">WooCommerce Subscriptions</a>.', 'woocommerce-payments' ),
				'https://woocommerce.com/products/woocommerce-subscriptions/'
			);
		} elseif ( version_compare( $wcpay_version, '9.8.0', '<' ) ) {
			$message = sprintf(
				/* translators: %1$s: WooCommerce Subscriptions */
				__( 'WooPayments no longer allows customers to create new subscriptions. Beginning in version 9.8, billing for existing customer subscriptions will no longer be supported. To ensure there is no interruption of service, please install <a target="_blank" href="%1$s">WooCommerce Subscriptions</a>.', 'woocommerce-payments' ),
				'https://woocommerce.com/products/woocommerce-subscriptions/'
			);
		} elseif ( version_compare( $wcpay_version, '9.9.0', '<' ) ) {
			$message = sprintf(
				/* translators: %1$s: WooCommerce Subscriptions */
				__( 'WooPayments no longer supports billing for existing customer subscriptions. All subscriptions data is read-only. Please install <a target="_blank" href="%1$s">WooCommerce Subscriptions</a> to continue managing your subscriptions.', 'woocommerce-payments' ),
				'https://woocommerce.com/products/woocommerce-subscriptions/'
			);
		} else {
			$message = sprintf(
				/* translators: %1$s: WooCommerce Subscriptions */
				__( 'WooPayments no longer supports subscriptions capabilities and subscriptions data can no longer be accessed. Please install <a target="_blank" href="%1$s">WooCommerce Subscriptions</a> to continue managing your subscriptions.', 'woocommerce-payments' ),
				'https://woocommerce.com/products/woocommerce-subscriptions/'
			);
		}

		WC_Payments::display_admin_notice( $message, 'notice-warning' );
	}

	/**
	 * Check if the current page is subscription-related.
	 *
	 * @return bool
	 */
	private function is_subscription_page() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		// Check if we're on the subscriptions list table.
		if ( false !== strpos( $screen->id, 'edit-shop_subscription' ) ) {
			return true;
		}

		// Check if we're on the edit subscription page.
		if ( false !== strpos( $screen->id, 'shop_subscription' ) ) {
			return true;
		}

		// Check if we're on the WooCommerce > Settings > Subscriptions page.
		if ( 'woocommerce_page_wc-settings' === $screen->id && isset( $_GET['tab'] ) && 'subscriptions' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return false;
	}
}
