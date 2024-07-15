<?php
/**
 * Class WC_Payments_Payment_Request_Session
 *
 * @package WooCommerce\Payments
 */

use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Payments_Payment_Request_Session class.
 */
class WC_Payments_Payment_Request_Session {
	/**
	 * Init the hooks.
	 *
	 * @return void
	 */
	public function init() {
		// adding this filter with a higher priority than the session handler of the Store API.
		add_filter( 'woocommerce_session_handler', [ $this, 'add_payment_request_store_api_session_handler' ], 20 );
		add_filter( 'wp', [ $this, 'maybe_avoid_emptying_cart' ] );
		add_filter( 'rest_post_dispatch', [ $this, 'store_api_headers' ], 10, 3 );
//		add_filter( 'rest_pre_dispatch', [ $this, 'store_api_remove_cart_token' ], 10, 3 );
	}

	/**
	 * Generates a session token for the response headers.
	 *
	 * @return string
	 */
	protected function get_session_token() {
		return JsonWebToken::create(
			[
				'session_id' => WC()->session->session_id,
				'exp'        => time() + intval( apply_filters( 'wc_session_expiration', DAY_IN_SECONDS * 2 ) ),
				'iss'        => 'woopayments/product-page',
			],
			'@' . wp_salt()
		);
	}

	/**
	 * Removes the "remove cart contents" filter on the order received page, if the order has been placed with the PRBs on a product page.
	 */
	public function maybe_avoid_emptying_cart() {
		// TODO: ensure we're avoiding this only for orders placed with the PRBs.
		if ( !is_order_received_page() ) {
			return;
		}

		remove_action( 'template_redirect', 'wc_clear_cart_after_payment', 20 );
	}
//
//	/**
//	 * @param mixed            $response Response to replace the requested version with.
//	 * @param \WP_REST_Server  $server Server instance.
//	 * @param \WP_REST_Request $request Request used to generate the response.
//	 *
//	 * @return mixed
//	 */
//	public function store_api_remove_cart_token( $response, $server, $request ) {
//		if ( ! \WC_Payments_Utils::is_store_api_request() ) {
//			return $response;
//		}
//
//		if(!isset($_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION'])) {
//			return $response;
//		}
//
//		$nonce = $request->get_header( 'X-WooPayments-Tokenized-Cart-Session-Nonce' );
//		if ( ! wp_verify_nonce( $nonce, 'tokenized_cart_session_nonce' ) ) {
//			return $response;
//		}
//
//		$request->set_header( 'Cart-Token', '' );
//
//		return $response;
//	}

	/**
	 * Adding the session key to the Store API response, to ensure the session can be retrieved later.
	 *
	 * @param mixed            $response Response to replace the requested version with.
	 * @param \WP_REST_Server  $server Server instance.
	 * @param \WP_REST_Request $request Request used to generate the response.
	 *
	 * @return mixed
	 */
	public function store_api_headers( $response, $server, $request ) {
		if ( ! \WC_Payments_Utils::is_store_api_request() ) {
			return $response;
		}

		$nonce = $request->get_header( 'X-WooPayments-Tokenized-Cart-Session-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'tokenized_cart_session_nonce' ) ) {
			return $response;
		}

		$response->header( 'X-WooPayments-Tokenized-Cart-Session', $this->get_session_token() );

		return $response;
	}

	/**
	 * This filter is used to add a custom session handler before processing Store API request callbacks.
	 * This is only necessary because the Store API SessionHandler currently doesn't provide an `init_session_cookie` method.
	 *
	 * @param string $default_session_handler The default session handler class name.
	 *
	 * @return string The session handler class name.
	 */
	public function add_payment_request_store_api_session_handler( $default_session_handler ) {
		$nonce = wc_clean( wp_unslash( $_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION_NONCE'] ?? null ) );
		if ( ! wp_verify_nonce( $nonce, 'tokenized_cart_session_nonce' ) ) {
			return $default_session_handler;
		}

		if ( ! \WC_Payments_Utils::is_store_api_request() ) {
			return $default_session_handler;
		}

		if ( ! class_exists( JsonWebToken::class ) ) {
			return $default_session_handler;
		}

		// checking if the token is valid, if it's provided.
		// there can also be a case where the token is not provided, but we should still use the custom session handler.
		$cart_token = wc_clean( wp_unslash( $_SERVER['HTTP_X_WOOPAYMENTS_TOKENIZED_CART_SESSION'] ?? null ) );
		if (
			$cart_token && ! JsonWebToken::validate( $cart_token, '@' . wp_salt() )
		) {
			return $default_session_handler;
		}

		// ensures cart contents aren't merged across different sessions for the same customer.
//		add_filter( 'woocommerce_persistent_cart_enabled', '__return_false' );

		require_once WCPAY_ABSPATH . '/includes/class-wc-payments-payment-request-session-handler.php';

		return WC_Payments_Payment_Request_Session_Handler::class;
	}
}
