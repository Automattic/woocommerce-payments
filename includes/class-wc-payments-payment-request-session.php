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
	public static function init() {
		add_filter( 'woocommerce_session_handler', [ __CLASS__, 'add_payment_request_store_api_session_handler' ], 20 );
	}

	/**
	 * This filter is used to add a custom session handler before processing Store API request callbacks.
	 * This is only necessary because the Store API SessionHandler currently doesn't provide an `init_session_cookie` method.
	 *
	 * @param string $default_session_handler The default session handler class name.
	 *
	 * @return string The session handler class name.
	 */
	public static function add_payment_request_store_api_session_handler( $default_session_handler ) {
		$cart_token = wc_clean( wp_unslash( $_SERVER['HTTP_PRB_CART_TOKEN'] ?? null ) );

		if ( ! $cart_token ) {
			return $default_session_handler;
		}

		if ( ! \WC_Payments_Utils::is_store_api_request() ) {
			return $default_session_handler;
		}

		if ( ! class_exists( JsonWebToken::class ) ) {
			return $default_session_handler;
		}

		// checking if the token is valid.
		if (
			! JsonWebToken::validate( $cart_token, '@' . wp_salt() )
		) {
			return $default_session_handler;
		}

		return PaymentRequestSessionHandler::class;
	}
}
