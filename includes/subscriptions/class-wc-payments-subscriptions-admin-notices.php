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
	 * Initialize the class and attach callbacks.
	 */
	public function __construct() {
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

		$message = sprintf(
			/* translators: %1$s: WooCommerce Subscriptions, %2$s: WooPayments */
			__( 'We\'re no longer supporting bundled subscriptions with %2$s. To continue using Stripe Billing, please install the standalone %1$s plugin.', 'woocommerce-payments' ),
			'WooCommerce Subscriptions',
			'WooPayments'
		);

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

		return false;
	}
}
