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
		if ( ! $this->is_bundled_subscriptions_enabled() ) {
			return;
		}

		$wcpay_version = $this->get_wcpay_version();
		// if wcpay version is > 10.2.99, bail to not show the notice indefinitely.
		if ( version_compare( $wcpay_version, '10.2.99', '>' ) ) {
			return;
		}

		$message = '';

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
	 * Check if the current page is a subscription-related page.
	 *
	 * @return bool
	 */
	protected function is_subscription_page() {
		$screen = $this->get_screen_id();

		if ( ! $screen ) {
			return false;
		} elseif ( false !== strpos( $screen, 'edit-shop_subscription' ) ) {
			return true;
		} elseif ( false !== strpos( $screen, 'shop_subscription' ) ) {
			return true;
		} elseif ( 'woocommerce_page_wc-settings' === $screen && isset( $_GET['tab'] ) && 'subscriptions' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return false;
	}

	/**
	 * Get the current screen ID.
	 *
	 * @return string|false
	 */
	protected function get_screen_id() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		return $screen->id;
	}

	/**
	 * Get the WooPayments version.
	 *
	 * @return string
	 */
	protected function get_wcpay_version() {
		return WC_Payments::get_file_version( WCPAY_PLUGIN_FILE );
	}

	/**
	 * Check if bundled subscriptions are enabled.
	 *
	 * This checks for either WCPay Subscriptions or Stripe Billing being enabled,
	 * as both represent the bundled subscription functionality.
	 *
	 * @return bool
	 */
	protected function is_bundled_subscriptions_enabled() {
		$has_bundled_subs = WC_Payments_Features::is_wcpay_subscriptions_enabled() || WC_Payments_Features::is_stripe_billing_enabled();
		$has_wc_subs      = class_exists( 'WC_Subscriptions' );

		return $has_bundled_subs && ! $has_wc_subs;
	}
}
