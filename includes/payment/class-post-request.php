<?php
/**
 * Class Post_Request
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Payment;

/**
 * A bunch of helpers for retrieving $_POST parameters.
 */
class Post_Request {
	// phpcs:disable WordPress.Security.NonceVerification.Missing

	/**
	 * Loads the ID of an order from the request.
	 *
	 * @return int|null
	 */
	public function get_order_id() {
		return isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : null;
	}

	/**
	 * Loads the fingerprint from POST.
	 *
	 * @return string|null Either a string(token), empty string (needs verification), or null if not present at all.
	 */
	public function get_fingerprint() {
		if ( empty( $_POST['wcpay-fingerprint'] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$normalized = wc_clean( $_POST['wcpay-fingerprint'] );
		return is_string( $normalized ) ? $normalized : '';
	}

	/**
	 * Returns the fraud prevention token from post.
	 *
	 * @return string An empty (to force checks) or non-empty string.
	 */
	public function get_fraud_prevention_token() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return $_POST['wcpay-fraud-prevention-token'] ?? ''; // Empty string to force checks. Null means skip.
	}

	/**
	 * Loads the provided WooPay intent ID from POST, if any.
	 *
	 * @return string
	 */
	public function get_woopay_intent_id() {
		return sanitize_user(
			wp_unslash(
				// The sanitize_user call here is deliberate: it seems the most appropriate sanitization function
				// for a string that will only contain latin alphanumeric characters and underscores.
				$_POST['platform-checkout-intent'] ?? ''
			),
			true
		);
	}

	/**
	 * Loads an intent ID from the request.
	 *
	 * @return string|null
	 */
	public function get_checkout_intent_id() {
		return isset( $_POST['wc_payment_intent_id'] )
			? wc_clean( wp_unslash( $_POST['wc_payment_intent_id'] ) )
			: null;
	}

	/**
	 * Loads an intent ID during a redirect.
	 *
	 * @todo This should use the same parameter as the checkout intent ID.
	 * @return string|null
	 */
	public function get_redirect_intent_id() {
		if ( ! isset( $_POST['intent_id'] ) ) {
			return null;
		}

		return sanitize_text_field( wp_unslash( $_POST['intent_id'] ) );
	}

	/**
	 * Returns the ID of the provided payment method.
	 *
	 * @return string|null
	 */
	public function get_payment_method_id() {
		if ( ! isset( $_POST['payment_method_id'] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return wp_unslash( $_POST['payment_method_id'] );

	}

	/**
	 * Loads the selected UPE payment method type.
	 *
	 * @return string
	 */
	public function get_selected_payment_type() {
		return ! empty( $_POST['wcpay_selected_upe_payment_type'] )
			? wc_clean( wp_unslash( $_POST['wcpay_selected_upe_payment_type'] ) )
			: '';
	}

	/**
	 * Returns the payment country, provided by UPE to pre-calculate application fees.
	 *
	 * @return string|null
	 */
	public function get_payment_country() {
		return ! empty( $_POST['wcpay_payment_country'] )
			? wc_clean( wp_unslash( $_POST['wcpay_payment_country'] ) )
			: null;
	}

	// phpcs:enable WordPress.Security.NonceVerification.Missing
}
