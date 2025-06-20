<?php
/**
 * WooCommerce Payments VAT Redirect Service.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

defined( 'ABSPATH' ) || exit; // block direct access.

/**
 * Service class for handling VAT details redirect functionality.
 *
 * @todo This is a temporary solution that will be replaced by a dedicated VAT settings section.
 *       Remove this class when the permanent solution is implemented.
 */
class WC_Payments_VAT_Redirect_Service {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'template_redirect', [ $this, 'maybe_redirect' ], 1 );
	}

	/**
	 * Handles redirection to VAT details modal if needed.
	 *
	 * @return void
	 */
	public function maybe_redirect(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['vat-details-redirect'] ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// skip REST API calls.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		// skip AJAX requests.
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}

		// require login + capability.
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_safe_redirect( wp_login_url( home_url( add_query_arg( null, null ) ) ) );
			exit;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( 'show' === sanitize_text_field( wp_unslash( $_GET['vat-details-redirect'] ) ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments&vat-details-modal=true' ) );
			exit;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
}
