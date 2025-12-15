<?php
/**
 * Class WC_Payments_Express_Checkout_Avatax_Compatibility
 *
 * Handles Avatax plugin compatibility for Express Checkout (Apple Pay, Google Pay).
 *
 * @package WooCommerce\Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Avatax compatibility for Express Checkout.
 *
 * Avatax plugin's tax calculation may be skipped during Express Checkout because it doesn't
 * recognize Store API requests as checkout context. This class forces Avatax to calculate
 * taxes during Express Checkout by adding filters that return true for the readiness checks.
 */
class WC_Payments_Express_Checkout_Avatax_Compatibility {

	/**
	 * Initialize hooks if Avatax plugin is active.
	 *
	 * @return void
	 */
	public function maybe_init() {
		if ( ! $this->is_avatax_plugin_active() ) {
			return;
		}

		add_filter( 'rest_pre_dispatch', [ $this, 'maybe_add_avatax_filters' ], 5, 3 );
	}

	/**
	 * Adds Avatax compatibility filters for Express Checkout.
	 *
	 * @param mixed            $response Response to replace the requested version with.
	 * @param \WP_REST_Server  $server Server instance.
	 * @param \WP_REST_Request $request Request used to generate the response.
	 *
	 * @return mixed
	 */
	public function maybe_add_avatax_filters( $response, $server, $request ) {
		// Only add filters if we're in an Express Checkout context.
		if ( ! $this->is_express_checkout_context() ) {
			return $response;
		}

		// Force Avatax to calculate taxes during Express Checkout.
		// These filters ensure Avatax recognizes the Store API checkout as a valid checkout context.
		add_filter( 'wc_avatax_cart_needs_calculation', '__return_true' );
		add_filter( 'wc_avatax_checkout_ready_for_calculation', '__return_true' );

		return $response;
	}

	/**
	 * Check if Avatax plugin is active.
	 *
	 * @return bool True if Avatax is active, false otherwise.
	 */
	private function is_avatax_plugin_active() {
		return class_exists( 'WC_AvaTax_Loader' ) || function_exists( 'wc_avatax' );
	}

	/**
	 * Check if we're in an express checkout context.
	 *
	 * @return bool True if we're in an express checkout context, false otherwise.
	 */
	private function is_express_checkout_context() {
		// Only proceed if this is a Store API request.
		if ( ! WC_Payments_Utils::is_store_api_request() ) {
			return false;
		}

		// Check for the 'X-WooPayments-Tokenized-Cart' header using superglobals.
		if ( 'true' !== sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART'] ?? '' ) ) ) {
			return false;
		}

		// Verify the nonce from the 'X-WooPayments-Tokenized-Cart-Nonce' header using superglobals.
		$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_NONCE'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'woopayments_tokenized_cart_nonce' ) ) {
			return false;
		}

		return true;
	}
}
