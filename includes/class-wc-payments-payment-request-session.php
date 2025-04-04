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
	 * Name of the parameter added to the "order received" page for orders placed with the custom session handler on product pages.
	 *
	 * @var string Name of the parameter.
	 */
	private static $prevent_empty_cart_parameter = 'woopayments-custom-session';

	/**
	 * Used as a temporary reference to cart data, so it can be restored later.
	 *
	 * @var null|WC_Cart Temporary reference to cart data.
	 */
	private $cart_clone = null;

	/**
	 * Init the hooks.
	 *
	 * @return void
	 */
	public function init() {
		// adding this filter with a higher priority than the session handler of the Store API.
		add_filter( 'woocommerce_session_handler', [ $this, 'add_payment_request_store_api_session_handler' ], 20 );

		// checking to ensure we're not erasing the cart on the "order received" page.
		if ( $this->is_custom_session_order_received_page() ) {
			add_filter( 'woocommerce_persistent_cart_enabled', '__return_false' );
			add_filter( 'woocommerce_cart_session_initialize', '__return_false' );
			add_action(
				'woocommerce_before_cart_emptied',
				[ $this, 'save_old_cart_data_for_restore' ]
			);
			add_action(
				'woocommerce_cart_emptied',
				[ $this, 'restore_old_cart_data' ]
			);
		}
	}

	/**
	 * Saves an instance of the current cart, so it can be restored later.
	 * Used on the "order received" page for orders placed with the PRB. The "order received" page empties the cart, otherwise.
	 *
	 * @return void
	 */
	public function save_old_cart_data_for_restore() {
		$this->cart_clone = clone WC()->cart;
	}

	/**
	 * Restores the cart saved previously.
	 *
	 * @return void
	 */
	public function restore_old_cart_data() {
		if ( ! $this->cart_clone ) {
			return;
		}

		WC()->cart->cart_contents         = $this->cart_clone->cart_contents;
		WC()->cart->removed_cart_contents = $this->cart_clone->removed_cart_contents;
		WC()->cart->applied_coupons       = $this->cart_clone->applied_coupons;

		$this->cart_clone = null;
	}

	/**
	 * Ensuring that the return URL for the "order received" page contains a query string parameter
	 * that can later be identified to ensure we don't clear the cart.
	 * This function is only executed when we're using the custom session handler on Store API requests.
	 *
	 * @param string $return_url The URL for the "Order received" page.
	 *
	 * @return string
	 */
	public function store_api_order_received_return_url( $return_url ) {
		return add_query_arg( self::$prevent_empty_cart_parameter, '1', $return_url );
	}

	/**
	 * Check if the $_SERVER global has order received URL slug in its 'REQUEST_URI' value - just like `wcs_is_order_received_page`.
	 *
	 * Similar to WooCommerce's is_custom_session_order_received_page(), but can be used before the $wp's query vars are setup, which is essential
	 * when preventing the cart from being emptied on the "order received" page, if the order has been placed with WooPayments GooglePay/ApplePay on the product page.
	 *
	 * @return bool
	 **/
	private function is_custom_session_order_received_page() {
		// ignoring because we're not storing the value anywhere, just checking its existence.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		return ( false !== strpos( $_SERVER['REQUEST_URI'], 'order-received' ) ) && ( false !== strpos( $_SERVER['REQUEST_URI'], self::$prevent_empty_cart_parameter ) );
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
	 * Adding the session key to the Store API response, to ensure the session can be retrieved later.
	 *
	 * @param mixed $response Response to replace the requested version with.
	 *
	 * @return mixed
	 */
	public function store_api_headers( $response ) {
		$response->header( 'X-WooPayments-Tokenized-Cart-Session', $this->get_session_token() );

		return $response;
	}

	/**
	 * Adding the session key to the Store API response, to ensure the session can be retrieved later.
	 *
	 * @param mixed            $response Response to replace the requested version with.
	 * @param \WP_REST_Server  $server Server instance.
	 * @param \WP_REST_Request $request Request used to generate the response.
	 *
	 * @return mixed
	 */
	public function maybe_clear_cart_data( $response, $server, $request ) {
		if ( $request->get_header( 'X-WooPayments-Tokenized-Cart-Is-Ephemeral-Cart' ) === '1' ) {
			// the customer id value doesn't matter.
			// in this case, we'll be using the `WC_Payments_Payment_Request_Session_Handler` session handler,
			// which will use the correct customer ID to delete.
			// I am specifically calling `delete_session` instead of `forget_session` or `destroy_session`,
			// because those methods might delete the customer's cookies (which we want to keep).
			WC()->session->delete_session( 0 );
			WC()->cart->empty_cart();
		}

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
		if ( ! wp_verify_nonce( $nonce, 'woopayments_tokenized_cart_session_nonce' ) ) {
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
		add_filter( 'woocommerce_persistent_cart_enabled', '__return_false' );
		// when an order is placed via the Store API on product pages, we need to slightly modify the "order received" URL.
		add_filter( 'woocommerce_get_return_url', [ $this, 'store_api_order_received_return_url' ] );
		// ensuring that the `X-WooPayments-Tokenized-Cart-Session` response header is added to the response.
		add_filter( 'rest_post_dispatch', [ $this, 'store_api_headers' ] );
		// clearing the cart contents if the request is made just to fetch product attributes and prices.
		add_filter( 'rest_post_dispatch', [ $this, 'maybe_clear_cart_data' ], 10, 3 );

		require_once WCPAY_ABSPATH . '/includes/class-wc-payments-payment-request-session-handler.php';

		return WC_Payments_Payment_Request_Session_Handler::class;
	}
}
