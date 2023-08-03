<?php
/**
 * Class WooPay_Session.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay;

use Automattic\Jetpack\Connection\Rest_Authentication;
use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;
use WCPay\Logger;
use WC_Payments;
use WC_Payments_Features;
use WCPay\Platform_Checkout\SessionHandler;
use WP_REST_Request;

/**
 * Class responsible for handling woopay sessions.
 * This class should be loaded as soon as possible so the correct session is loaded.
 * So don't load it in the WC_Payments::init() function.
 */
class WooPay_Session {

	const STORE_API_NAMESPACE_PATTERN = '@^wc/store(/v[\d]+)?$@';

	/**
	 * The Store API route patterns that should be handled by the WooPay session handler.
	 */
	const STORE_API_ROUTE_PATTERNS = [
		'@^\/wc\/store(\/v[\d]+)?\/cart$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/apply-coupon$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/remove-coupon$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/select-shipping-rate$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/update-customer$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/update-item$@',
		'@^\/wc\/store(\/v[\d]+)?\/cart\/extensions$@',
		'@^\/wc\/store(\/v[\d]+)?\/checkout$@',
	];

	/**
	 * Init the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'determine_current_user', [ __CLASS__, 'determine_current_user_for_woopay' ], 20 );
		add_filter( 'rest_request_before_callbacks', [ __CLASS__, 'add_woopay_store_api_session_handler' ], 10, 3 );
		add_action( 'woocommerce_order_payment_status_changed', [ __CLASS__, 'remove_order_customer_id_on_requests_with_verified_email' ] );
		add_action( 'woopay_restore_order_customer_id', [ __CLASS__, 'restore_order_customer_id_from_requests_with_verified_email' ] );

		register_deactivation_hook( WCPAY_PLUGIN_FILE, [ __CLASS__, 'run_and_remove_woopay_restore_order_customer_id_schedules' ] );
	}

	/**
	 * This filter is used to add a custom session handler before processing Store API request callbacks.
	 * This is only necessary because the Store API SessionHandler currently doesn't provide an `init_session_cookie` method.
	 *
	 * @param mixed           $response The response object.
	 * @param mixed           $handler The handler used for the response.
	 * @param WP_REST_Request $request The request used to generate the response.
	 *
	 * @return mixed
	 */
	public static function add_woopay_store_api_session_handler( $response, $handler, WP_REST_Request $request ) {
		$cart_token = $request->get_header( 'Cart-Token' );

		if (
			$cart_token &&
			self::is_store_api_request() &&
			class_exists( JsonWebToken::class ) &&
			JsonWebToken::validate( $cart_token, '@' . wp_salt() )
		) {
			add_filter(
				'woocommerce_session_handler',
				function ( $session_handler ) {
					return SessionHandler::class;
				},
				20
			);
		}

		return $response;
	}

	/**
	 * Sets the current user as the user sent via the api from WooPay if present.
	 *
	 * @param \WP_User|null|int $user user to be used during the request.
	 *
	 * @return \WP_User|null|int
	 */
	public static function determine_current_user_for_woopay( $user ) {
		if ( ! self::is_request_from_woopay() || ! self::is_store_api_request() ) {
			return $user;
		}

		if ( ! self::is_woopay_enabled() ) {
			return $user;
		}

		// Validate that the request is signed properly.
		if ( ! self::has_valid_request_signature() ) {
			Logger::log( __( 'WooPay request is not signed correctly.', 'woocommerce-payments' ) );
			wp_die( esc_html__( 'WooPay request is not signed correctly.', 'woocommerce-payments' ), 401 );
		}

		$cart_token_user_id = self::get_user_id_from_cart_token();
		if ( null === $cart_token_user_id ) {
			return $user;
		}

		return $cart_token_user_id;
	}

	/**
	 * Returns the user ID from the cart token.
	 *
	 * @return int|null The User ID or null if there's no cart token in the request.
	 */
	public static function get_user_id_from_cart_token() {
		$payload = self::get_payload_from_cart_token();

		if ( null === $payload ) {
			return null;
		}

		$session_handler = new SessionHandler();
		$session_data    = $session_handler->get_session( $payload->user_id );
		$customer        = maybe_unserialize( $session_data['customer'] );

			// If the token is already authenticated, return the customer ID.
		if ( is_numeric( $customer['id'] ) && intval( $customer['id'] ) > 0 ) {
			return intval( $customer['id'] );
		}

		$woopay_verified_email_address = self::get_woopay_verified_email_address();
		$enabled_adapted_extensions    = get_option( WooPay_Scheduler::ENABLED_ADAPTED_EXTENSIONS_OPTION_NAME, [] );

		// If the email is verified on WooPay, matches session email (set during the redirection),
		// and the store has an adapted extension installed,
		// return the user to get extension data without authentication.
		if ( count( $enabled_adapted_extensions ) > 0 && null !== $woopay_verified_email_address && ! empty( $customer['email'] ) ) {
			$user = get_user_by( 'email', $woopay_verified_email_address );

			if ( $woopay_verified_email_address === $customer['email'] && $user ) {
				// Remove Gift Cards session cache to load account gift cards.
				add_filter( 'woocommerce_gc_account_session_timeout_minutes', '__return_false' );

				return $user->ID;
			}
		}

		return null;
	}

	/**
	 * Prevent set order customer ID on requests with
	 * email verified to skip the login screen on the TYP.
	 * After 10 minutes, the customer ID will be restored
	 * and the user will need to login to access the TYP.
	 *
	 * @param \WC_Order $order_id The order ID being updated.
	 */
	public static function remove_order_customer_id_on_requests_with_verified_email( $order_id ) {
		$woopay_verified_email_address = self::get_woopay_verified_email_address();

		if ( null === $woopay_verified_email_address ) {
			return;
		}

		if ( ! self::is_woopay_enabled() ) {
			return;
		}

		if ( ! self::is_request_from_woopay() || ! self::is_store_api_request() ) {
			return;
		}

		$enabled_adapted_extensions = get_option( WooPay_Scheduler::ENABLED_ADAPTED_EXTENSIONS_OPTION_NAME, [] );

		if ( count( $enabled_adapted_extensions ) === 0 ) {
			return;
		}

		$payload = self::get_payload_from_cart_token();

		if ( null === $payload ) {
			return;
		}

		$order = wc_get_order( $order_id );

		// Guest users user_id on the cart token payload looks like "t_hash" and the order
		// customer id is 0, logged in users is the real user id in both cases.
		$user_is_logged_in = $payload->user_id === $order->get_customer_id();

		if ( ! $user_is_logged_in && $woopay_verified_email_address === $order->get_billing_email() ) {
			$order->add_meta_data( 'woopay_merchant_customer_id', $order->get_customer_id(), true );
			$order->set_customer_id( 0 );
			$order->save();

			wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, 'woopay_restore_order_customer_id', [ $order_id ] );
		}
	}

	/**
	 * Restore the order customer ID after 10 minutes
	 * on requests with email verified.
	 *
	 * @param \WC_Order $order_id The order ID being updated.
	 */
	public static function restore_order_customer_id_from_requests_with_verified_email( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order->meta_exists( 'woopay_merchant_customer_id' ) ) {
			return;
		}

		$order->set_customer_id( $order->get_meta( 'woopay_merchant_customer_id' ) );
		$order->delete_meta_data( 'woopay_merchant_customer_id' );
		$order->save();
	}

	/**
	 * Restore all WooPay verified email orders customer ID
	 * and disable the schedules when plugin is disabled.
	 */
	public static function run_and_remove_woopay_restore_order_customer_id_schedules() {
		$args = [
			'meta_key' => 'woopay_merchant_customer_id', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'return'   => 'ids',
		];

		$order_ids = wc_get_orders( $args );

		if ( ! empty( $order_ids ) ) {
			foreach ( $order_ids as $order_id ) {
				self::restore_order_customer_id_from_requests_with_verified_email( $order_id );
			}
		}

		wp_clear_scheduled_hook( 'woopay_restore_order_customer_id' );
	}

	/**
	 * Returns the payload from a cart token.
	 *
	 * @return object|null The cart token payload if it's valid.
	 */
	private static function get_payload_from_cart_token() {
		if ( ! isset( $_SERVER['HTTP_CART_TOKEN'] ) ) {
			return null;
		}

		if ( ! class_exists( JsonWebToken::class ) ) {
			return null;
		}

		$cart_token = wc_clean( wp_unslash( $_SERVER['HTTP_CART_TOKEN'] ) );

		if ( $cart_token && JsonWebToken::validate( $cart_token, '@' . wp_salt() ) ) {
			$payload = JsonWebToken::get_parts( $cart_token )->payload;

			if ( empty( $payload ) ) {
				return null;
			}

			// Store API namespace is used as the token issuer.
			if ( ! preg_match( self::STORE_API_NAMESPACE_PATTERN, $payload->iss ) ) {
				return null;
			}

			return $payload;
		}

		return null;
	}

	/**
	 * Get the WooPay verified email address from the header.
	 *
	 * @return string|null The WooPay verified email address if it's set.
	 */
	private static function get_woopay_verified_email_address() {
		$has_woopay_verified_email_address = isset( $_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS'] );

		return $has_woopay_verified_email_address ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WOOPAY_VERIFIED_EMAIL_ADDRESS'] ) ) : null;
	}

	/**
	 * Returns true if the request that's currently being processed is a Store API request, false
	 * otherwise.
	 *
	 * @return bool True if request is a Store API request, false otherwise.
	 */
	private static function is_store_api_request(): bool {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}

		$url_parts    = wp_parse_url( esc_url_raw( $_SERVER['REQUEST_URI'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$request_path = rtrim( $url_parts['path'], '/' );
		$rest_route   = str_replace( trailingslashit( rest_get_url_prefix() ), '', $request_path );

		foreach ( self::STORE_API_ROUTE_PATTERNS as $pattern ) {
			if ( 1 === preg_match( $pattern, $rest_route ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns true if the request that's currently being processed is from WooPay, false
	 * otherwise.
	 *
	 * @return bool True if request is from WooPay.
	 */
	private static function is_request_from_woopay(): bool {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) && 'WooPay' === $_SERVER['HTTP_USER_AGENT'];
	}

	/**
	 * Returns true if the request that's currently being processed is signed with the blog token.
	 *
	 * @return bool True if the request signature is valid.
	 */
	private static function has_valid_request_signature() {
		return apply_filters( 'wcpay_woopay_is_signed_with_blog_token', Rest_Authentication::is_signed_with_blog_token() );
	}

	/**
	 * Returns true if WooPay is enabled, false otherwise.
	 *
	 * @return bool True if WooPay is enabled, false otherwise.
	 */
	private static function is_woopay_enabled(): bool {
		return WC_Payments_Features::is_woopay_eligible() && 'yes' === WC_Payments::get_gateway()->get_option( 'platform_checkout', 'no' );
	}
}
