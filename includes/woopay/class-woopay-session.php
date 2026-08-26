<?php
/**
 * Class WooPay_Session.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay;

use Automattic\WooCommerce\StoreApi\RoutesController;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Utilities\JsonWebToken;
use Jetpack_Options;
use WCPay\Blocks_Data_Extractor;
use WCPay\Logger;
use WCPay\Platform_Checkout\SessionHandler;
use WCPay\Platform_Checkout\WooPay_Store_Api_Token;
use WCPay\WooPay\WooPay_Scheduler;
use WC_Customer;
use WC_Payments;
use WC_Payments_Customer_Service;
use WC_Payments_Features;
use WCPay\MultiCurrency\MultiCurrency;
use WP_REST_Request;

/**
 * Class responsible for handling woopay sessions.
 * This class should be loaded as soon as possible so the correct session is loaded.
 * So don't load it in the WC_Payments::init() function.
 */
class WooPay_Session {

	const STORE_API_NAMESPACE_PATTERN = '@^(wc/store(/v[\d]+)?|store-api)$@';

	const WOOPAY_SESSION_KEY = 'woopay-user-data';

	/**
	 * Request carries a valid Cart-Token.
	 */
	const AUTH_CART_TOKEN = 'cart_token';

	/**
	 * Request carries neither. Not authorized.
	 */
	const AUTH_NONE = 'none';

	/**
	 * How old a WooPay attestation envelope may be, in seconds.
	 *
	 * The envelope carries no nonce of its own, so freshness bounds how long a replay of
	 * an observed one stays interesting. Five minutes. Each envelope is also spent on
	 * first use — see `claim_attestation()` — so this bounds the window in which a
	 * *never-delivered* envelope could be raced, not one already used.
	 */
	const ATTESTATION_MAX_AGE = 300;

	/**
	 * Request parameter carrying a WooPay attestation envelope.
	 *
	 * Its own name rather than the `encrypted_data` key `get_user_email()` reads. That is
	 * an older exchange with a different payload and different rules, and one being taken
	 * for the other is the kind of mistake a shared name makes easy.
	 */
	const ATTESTATION_PARAM = 'woopay_attestation';

	/**
	 * Transient prefix recording that an attestation envelope has been spent.
	 */
	const ATTESTATION_CLAIM_PREFIX = 'wcpay_woopay_attestation_';

	/**
	 * Attestations already resolved on this request, keyed by envelope fingerprint.
	 *
	 * The permission check, the email lookup and the nonce gate each ask independently,
	 * and an envelope may only be spent once — so the answer has to be remembered rather
	 * than recomputed, or the second caller would see a replay of the first.
	 *
	 * @var array<string, array|null>
	 */
	private static $resolved_attestations = [];

	/**
	 * Order ID used for error handling.
	 *
	 * @var int|null
	 */
	private static $checkout_error_order_id = null;

	/**
	 * Whether the error handler has been registered.
	 *
	 * @var bool
	 */
	private static $is_error_handler_registered = false;

	/**
	 * Init the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'determine_current_user', [ __CLASS__, 'determine_current_user_for_woopay' ], 20 );
		add_filter( 'woocommerce_session_handler', [ __CLASS__, 'add_woopay_store_api_session_handler' ], 20 );
		add_action( 'woocommerce_order_payment_status_changed', [ __CLASS__, 'woopay_order_payment_status_changed' ] );
		add_action( 'woopay_restore_order_customer_id', [ __CLASS__, 'restore_order_customer_id_from_requests_with_verified_email' ] );
		add_filter( 'woocommerce_order_needs_payment', [ __CLASS__, 'woopay_trial_subscriptions_handler' ], 20, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', [ __CLASS__, 'catch_woopay_checkout_errors' ], 1, 1 );

		register_deactivation_hook( WCPAY_PLUGIN_FILE, [ __CLASS__, 'run_and_remove_woopay_restore_order_customer_id_schedules' ] );

		add_filter( 'automatewoo/referrals/referred_order_advocate', [ __CLASS__, 'automatewoo_refer_a_friend_referral_from_parameter' ] );
	}

	/**
	 * This filter is used to add a custom session handler before processing Store API request callbacks.
	 * This is only necessary because the Store API SessionHandler currently doesn't provide an `init_session_cookie` method.
	 *
	 * @param string $default_session_handler The default session handler class name.
	 *
	 * @return string The session handler class name.
	 */
	public static function add_woopay_store_api_session_handler( $default_session_handler ) {
		$cart_token = wc_clean( wp_unslash( $_SERVER['HTTP_CART_TOKEN'] ?? null ) );

		if (
		$cart_token &&
		self::is_request_from_woopay() &&
		\WC_Payments_Utils::is_store_api_request() &&
		class_exists( JsonWebToken::class ) &&
		JsonWebToken::validate( $cart_token, '@' . wp_salt() )
		) {
			return SessionHandler::class;
		}

		return $default_session_handler;
	}

	/**
	 * Sets the current user as the user sent via the api from WooPay if present.
	 *
	 * @param \WP_User|null|int $user user to be used during the request.
	 *
	 * @return \WP_User|null|int
	 */
	public static function determine_current_user_for_woopay( $user ) {
		if ( ! self::is_request_from_woopay() || ! \WC_Payments_Utils::is_store_api_request() ) {
			return $user;
		}

		if ( ! self::is_woopay_enabled() ) {
			return $user;
		}

		// Validate that the request is authenticated by a valid Cart-Token.
		if ( self::AUTH_NONE === self::get_request_auth_level() ) {
			$error = self::get_unauthenticated_request_error();

			$error_data = $error->get_error_data();

			Logger::log( 'WooPay request rejected: ' . $error->get_error_code() );

			// Spelled out rather than passing the WP_Error itself: only handlers that run it
			// through _wp_die_process_input() would unpack the code and status, and the
			// handler is filterable.
			wp_die(
				esc_html( $error->get_error_message() ),
				'',
				[
					'response' => absint( $error_data['status'] ),
					'code'     => esc_html( $error->get_error_code() ),
				]
			);
		}

		add_filter( 'wcpay_is_woopay_store_api_request', '__return_true' );

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

		/*
		 * If the token is already authenticated, return the customer ID.
		 *
		 * A Cart-Token on its own would resolve the account behind the session, which is a
		 * weaker pairing than this path used to require when every request was signed — so
		 * ask for the same store-minted nonce the verified-email branch below does.
		 *
		 * Worth being honest about the limit: WooPay sends `Nonce` and `Cart-Token` as
		 * headers on one request, so this does nothing against a caller who captured the
		 * whole request. What it stops is a Cart-Token on its own resolving a registered
		 * user.
		 *
		 * The nonce is `session_nonce` from the session payload, rotated from this store's
		 * own responses afterwards, so it stays bound to the same user across a checkout.
		 * A shopper whose session-creation identity differs from their cart session's falls
		 * back to guest, which detaches the order from their account — logged rather than
		 * silent, since that is the failure worth noticing here.
		 */
		if ( is_numeric( $customer['id'] ) && intval( $customer['id'] ) > 0 ) {
			$customer_id = intval( $customer['id'] );

			if ( ! self::has_store_minted_nonce_for_user( $customer_id ) ) {
				Logger::log( 'WooPay Cart-Token rejected: no store-minted nonce bound to the session customer. Resolving as guest.' );

				return null;
			}

			return $customer_id;
		}

		$woopay_verified_email_address = self::get_woopay_verified_email_address();
		$enabled_adapted_extensions    = get_option( WooPay_Scheduler::ENABLED_ADAPTED_EXTENSIONS_OPTION_NAME, [] );

		// If the email is verified on WooPay, matches session email (set during the redirection),
		// and the store has an adapted extension installed,
		// return the user to get extension data without authentication.
		if ( ( is_countable( $enabled_adapted_extensions ) ? count( $enabled_adapted_extensions ) : 0 ) > 0 && null !== $woopay_verified_email_address && ! empty( $customer['email'] ) ) {
			$user = get_user_by( 'email', $woopay_verified_email_address );

			if ( $woopay_verified_email_address === $customer['email'] && $user ) {
				/**
				 * This branch resolves a registered user from a request header, so the header
				 * has to be authenticated: require the nonce this store minted for that
				 * specific user (email_verified_session_nonce).
				 */
				if ( ! self::has_store_minted_nonce_for_user( (int) $user->ID ) ) {
					Logger::log( 'WooPay verified email header rejected: no store-minted nonce bound to the requested user.' );

					return null;
				}

				// Remove Gift Cards session cache to load account gift cards.
				add_filter( 'woocommerce_gc_account_session_timeout_minutes', '__return_false' );

				return $user->ID;
			}
		}

		return null;
	}

	/**
	 * Update order data for extensions which uses cookies,
	 * also prevent set order customer ID on requests with
	 * email verified to skip the login screen on the TYP.
	 * After 10 minutes, the customer ID will be restored
	 * and the user will need to login to access the TYP.
	 *
	 * @param int $order_id The order ID being updated.
	 */
	public static function woopay_order_payment_status_changed( $order_id ) {
		if ( ! self::is_woopay_enabled() ) {
			return;
		}

		if ( ! self::is_request_from_woopay() || ! \WC_Payments_Utils::is_store_api_request() ) {
			return;
		}

		$woopay_adapted_extensions = new WooPay_Adapted_Extensions();
		$woopay_adapted_extensions->update_order_extension_data( $order_id );

		$woopay_verified_email_address = self::get_woopay_verified_email_address();

		if ( null === $woopay_verified_email_address ) {
			return;
		}

		$enabled_adapted_extensions = get_option( WooPay_Scheduler::ENABLED_ADAPTED_EXTENSIONS_OPTION_NAME, [] );

		if ( ( is_countable( $enabled_adapted_extensions ) ? count( $enabled_adapted_extensions ) : 0 ) === 0 ) {
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
		// WooCommerce is disabled when disabling WCPay.
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$args = [
			'meta_key' => 'woopay_merchant_customer_id', //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'return'       => 'ids',
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
	 * Fix for AutomateWoo - Refer A Friend Add-on
	 * plugin when using link referrals.
	 *
	 * @param int $advocate_id The advocate ID.
	 *
	 * @return false|int|mixed The advocate ID or false if the request is not from WooPay.
	 */
	public static function automatewoo_refer_a_friend_referral_from_parameter( $advocate_id ) {
		if ( ! self::is_request_from_woopay() || ! \WC_Payments_Utils::is_store_api_request() ) {
			return $advocate_id;
		}

		if ( ! self::is_woopay_enabled() ) {
			return $advocate_id;
		}

		if ( empty( $_GET['automatewoo_referral_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}

		$automatewoo_referral = (int) wc_clean( wp_unslash( $_GET['automatewoo_referral_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		return $automatewoo_referral;
	}

	/**
	 * Process trial subscriptions for WooPay.
	 *
	 * @param bool      $needs_payment If the order needs payment.
	 * @param \WC_Order $order The order.
	 * @param array     $_unused_valid_order_statuses The valid order statuses.
	 */
	public static function woopay_trial_subscriptions_handler( $needs_payment, $order, $_unused_valid_order_statuses ) {
		if ( ! self::is_request_from_woopay() || ! \WC_Payments_Utils::is_store_api_request() ) {
			return $needs_payment;
		}

		if ( ! self::is_woopay_enabled() ) {
			return $needs_payment;
		}

		if ( ! class_exists( 'WC_Subscriptions_Cart' ) || $order->get_total() > 0 ) {
			return $needs_payment;
		}

		if ( \WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return true;
		}

		return $needs_payment;
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
	 * Returns the encrypted session request for the frontend.
	 *
	 * @return array The encrypted session request or an empty array if the server is not eligible for encryption.
	 */
	public static function get_frontend_init_session_request() {
		if ( ! extension_loaded( 'openssl' ) || ! function_exists( 'openssl_encrypt' ) ) {
			return [];
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$order_id      = ! empty( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : null;
		$key           = ! empty( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : null;
		$billing_email = ! empty( $_POST['billing_email'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_email'] ) ) : null;
		// phpcs:enable
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, Generic.Arrays.DisallowLongArraySyntax.Found
		$appearance = ! empty( $_POST['appearance'] ) ? self::array_map_recursive( array( __CLASS__, 'sanitize_string' ), $_POST['appearance'] ) : null;

		$session = self::get_init_session_request( $order_id, $key, $billing_email, null, $appearance );

		return WooPay_Utilities::encrypt_and_sign_data( $session );
	}

	/**
	 * Retrieves cart data from the current session.
	 *
	 * If the request doesn't come from WooPay, this uses the same strategy in
	 * `hydrate_from_api` on the Checkout Block to retrieve cart data.
	 *
	 * @param bool                 $is_pay_for_order Whether the request is for a pay-for-order session.
	 * @param int|null             $order_id Pay-for-order order ID.
	 * @param string|null          $key Pay-for-order key.
	 * @param string|null          $billing_email Pay-for-order billing email.
	 * @param WP_REST_Request|null $woopay_request The WooPay request object.
	 *
	 * @return array The cart data.
	 */
	private static function get_cart_data( $is_pay_for_order, $order_id, $key, $billing_email, $woopay_request ) {
		if ( ! $woopay_request ) {
			return ! $is_pay_for_order
			? rest_preload_api_request( [], '/wc/store/v1/cart' )['/wc/store/v1/cart']['body']
			: rest_preload_api_request( [], '/wc/store/v1/order/' . rawurlencode( $order_id ) . '?key=' . rawurlencode( $key ) . '&billing_email=' . rawurlencode( $billing_email ) )[ '/wc/store/v1/order/' . rawurlencode( $order_id ) . '?key=' . rawurlencode( $key ) . '&billing_email=' . rawurlencode( $billing_email ) ]['body'];
		}

		$cart_request = new WP_REST_Request( 'GET', '/wc/store/v1/cart' );
		$cart_request->set_header( 'Cart-Token', $woopay_request->get_header( 'cart_token' ) );
		return rest_do_request( $cart_request )->get_data();
	}

	/**
	 * Retrieves checkout data from the current session.
	 *
	 * If the request doesn't come from WooPay, this uses the same strategy in
	 * `hydrate_from_api` on the Checkout Block to retrieve checkout data.
	 *
	 * @param WP_REST_Request $woopay_request The WooPay request object.
	 * @return mixed The checkout data.
	 */
	private static function get_checkout_data( $woopay_request ) {
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

		if ( ! $woopay_request ) {
			$preloaded_checkout_data = rest_preload_api_request( [], '/wc/store/v1/checkout' );
			$checkout_data           = isset( $preloaded_checkout_data['/wc/store/v1/checkout'] ) ? $preloaded_checkout_data['/wc/store/v1/checkout']['body'] : '';
		} else {
			$checkout_request = new WP_REST_Request( 'GET', '/wc/store/v1/checkout' );
			$checkout_request->set_header( 'Cart-Token', $woopay_request->get_header( 'cart_token' ) );
			$checkout_data = rest_do_request( $checkout_request )->get_data();
		}

		remove_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

		return $checkout_data;
	}

	/**
	 * Retrieves the user email from the current session.
	 *
	 * @param \WP_User $user The user object.
	 * @return string The user email.
	 */
	public static function get_user_email( $user ) {
		// An email WooPay attested to outranks anything a caller can put in a plain request
		// parameter, and is the only source here that carries any proof of origin.
		$attested_email = self::get_woopay_attested_account_email();

		if ( null !== $attested_email ) {
			return $attested_email;
		}

		if ( ! empty( $_POST['email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return sanitize_email( wp_unslash( $_POST['email'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		if ( ! empty( $_GET['email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return sanitize_email( wp_unslash( $_GET['email'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		if ( ! empty( $_POST['encrypted_data'] ) && is_array( $_POST['encrypted_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			// phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$decrypted_data = WooPay_Utilities::decrypt_signed_data( $_POST['encrypted_data'] );

			if ( ! empty( $decrypted_data['user_email'] ) ) {
				return sanitize_email( wp_unslash( $decrypted_data['user_email'] ) );
			}
		}

		// Get the email from the customer object if it's available.
		if ( ! empty( WC()->customer ) ) {
			$billing_email = WC()->customer->get_billing_email();

			if ( ! empty( $billing_email ) ) {
				return $billing_email;
			}

			$customer_email = WC()->customer->get_email();

			if ( ! empty( $customer_email ) ) {
				return $customer_email;
			}
		}

		// As a last resort, we try to get the email from the customer logged in the store.
		if ( $user->exists() ) {
			return $user->user_email;
		}

		return '';
	}

	/**
	 * Returns the initial session request data.
	 *
	 * @param int|null             $order_id Pay-for-order order ID.
	 * @param string|null          $key Pay-for-order key.
	 * @param string|null          $billing_email Pay-for-order billing email.
	 * @param WP_REST_Request|null $woopay_request The WooPay request object.
	 * @param array|null           $appearance Merchant appearance, or null to use server-stored fallback.
	 * @param array                $font_rules Font CDN stylesheet URLs.
	 * @return array The initial session request data without email and user_session.
	 */
	public static function get_init_session_request( $order_id = null, $key = null, $billing_email = null, $woopay_request = null, $appearance = null, $font_rules = [] ) {
		// Fall back to server-stored appearance when no appearance was provided,
		// but only if global theme support is enabled.
		if ( null === $appearance && WC_Payments::get_gateway()->is_woopay_global_theme_support_enabled() ) {
			$appearance = \WC_Payments_Styles_Cache::get_woopay_appearance();
			$font_rules = \WC_Payments_Styles_Cache::get_woopay_font_rules();
		}

		// Fall back to server-extracted font rules when none were provided by the client.
		if ( empty( $font_rules ) && WC_Payments::get_gateway()->is_woopay_global_theme_support_enabled() ) {
			$font_rules = \WC_Payments_Styles_Cache::get_woopay_font_rules();
		}

		$user             = wp_get_current_user();
		$is_pay_for_order = null !== $order_id;
		$order            = wc_get_order( $order_id );
		$customer_id      = WC_Payments::get_customer_service()->get_customer_id_by_user_id( $user->ID );
		if ( null === $customer_id ) {
			// create customer.
			$customer_data = WC_Payments_Customer_Service::map_customer_data( null, new WC_Customer( $user->ID ) );
			$customer_id   = WC_Payments::get_customer_service()->create_customer_for_user( $user, $customer_data );
		}

		if ( WC_Payments_Features::is_customer_multi_currency_enabled() && 0 !== $user->ID ) {
			// Multicurrency selection is stored on user meta when logged in and WC session when logged out.
			// This code just makes sure that currency selection is available on WC session for WooPay.
			$currency      = get_user_meta( $user->ID, MultiCurrency::CURRENCY_META_KEY, true );
			$currency_code = strtoupper( $currency );

			if ( ! empty( $currency_code ) && WC()->session ) {
				WC()->session->set( MultiCurrency::CURRENCY_SESSION_KEY, $currency_code );
			}
		}

		$account_id = WC_Payments::get_account_service()->get_stripe_account_id();

		$site_logo_id      = get_theme_mod( 'custom_logo' );
		$site_logo_url     = $site_logo_id ? ( wp_get_attachment_image_src( $site_logo_id, 'full' )[0] ?? '' ) : '';
		$woopay_store_logo = WC_Payments::get_gateway()->get_option( 'platform_checkout_store_logo' );

		$store_logo = $site_logo_url;
		if ( ! empty( $woopay_store_logo ) ) {
			$store_logo = get_rest_url( null, 'wc/v3/payments/file/' . $woopay_store_logo );
		}

		include_once WCPAY_ABSPATH . 'includes/compat/blocks/class-blocks-data-extractor.php';
		$blocks_data_extractor = new Blocks_Data_Extractor();

		$cart_data     = self::get_cart_data( $is_pay_for_order, $order_id, $key, $billing_email, $woopay_request );
		$checkout_data = self::get_checkout_data( $woopay_request );
		$email         = self::get_user_email( $user );

		if ( $woopay_request ) {
			$order_id = $checkout_data['order_id'] ?? null;
		}

		$request = [
			'wcpay_version'        => WCPAY_VERSION_NUMBER,
			'user_id'              => $user->ID,
			'customer_id'          => $customer_id,
			'session_nonce'        => self::create_woopay_nonce( $user->ID ),
			'store_api_token'      => self::init_store_api_token(),
			'email'                => $email,
			'store_data'           => [
				'store_name'                     => get_bloginfo( 'name' ),
				'store_logo'                     => $store_logo,
				'custom_message'                 => self::get_formatted_custom_terms(),
				'blog_id'                        => Jetpack_Options::get_option( 'id' ),
				'blog_url'                       => get_site_url(),
				'blog_checkout_url'              => ! $is_pay_for_order ? wc_get_checkout_url() : $order->get_checkout_payment_url(),
				'blog_shop_url'                  => get_permalink( wc_get_page_id( 'shop' ) ),
				'blog_timezone'                  => wp_timezone_string(),
				'store_api_url'                  => self::get_store_api_url(),
				'account_id'                     => $account_id,
				'test_mode'                      => WC_Payments::mode()->is_test(),
				'capture_method'                 => empty( WC_Payments::get_gateway()->get_option( 'manual_capture' ) ) || 'no' === WC_Payments::get_gateway()->get_option( 'manual_capture' ) ? 'automatic' : 'manual',
				'is_subscriptions_plugin_active' => WC_Payments::get_gateway()->is_subscriptions_plugin_active(),
				'woocommerce_tax_display_cart'   => get_option( 'woocommerce_tax_display_cart' ),
				'ship_to_billing_address_only'   => wc_ship_to_billing_address_only(),
				'return_url'                     => ! $is_pay_for_order ? wc_get_cart_url() : $order->get_checkout_payment_url(),
				'blocks_data'                    => $blocks_data_extractor->get_data(),
				'checkout_schema_namespaces'     => $blocks_data_extractor->get_checkout_schema_namespaces(),
				'optional_fields_status'         => self::get_option_fields_status(),
			],
			'user_session'         => null,
			'preloaded_requests'   => ! $is_pay_for_order ? [
				'cart'     => $cart_data,
				'checkout' => $checkout_data,
			] : [
				'cart'     => $cart_data,
				'checkout' => [
					'order_id' => $order_id, // This is a workaround for the checkout order error. https://github.com/woocommerce/woocommerce-blocks/blob/04f36065b34977f02079e6c2c8cb955200a783ff/assets/js/blocks/checkout/block.tsx#L81-L83.
				],
			],
			'tracks_user_identity' => WC_Payments::woopay_tracker()->tracks_get_identity(),
			'appearance'           => $appearance,
			'font_rules'           => $font_rules,
		];

		$woopay_adapted_extensions = new WooPay_Adapted_Extensions();
		$request['extension_data'] = $woopay_adapted_extensions->get_extension_data();

		if ( ! empty( $email ) ) {
			// Save email in session to skip TYP verify email and check if
			// WooPay verified email matches.
			WC()->customer->set_billing_email( $email );
			WC()->customer->save();

			$woopay_adapted_extensions->init();
			$request['adapted_extensions'] = $woopay_adapted_extensions->get_adapted_extensions_data( $email );

			// $woopay_request is set only on the REST route, which hands this array straight
			// back to the caller in plaintext. The other two callers either encrypt the
			// payload or POST it to WooPay server-side, so the nonce is never disclosed to
			// whoever triggered them and no attestation is needed. See WOOPAY-463.
			$nonce_would_be_disclosed = null !== $woopay_request;

			if (
				! is_user_logged_in() &&
				count( $request['adapted_extensions'] ) > 0 &&
				( ! $nonce_would_be_disclosed || self::is_email_attested_by_woopay( $email ) )
			) {
				$store_user_email_registered = get_user_by( 'email', $email );

				if ( $store_user_email_registered ) {
					$request['email_verified_session_nonce'] = self::create_woopay_nonce( $store_user_email_registered->ID );
				}
			}
		}

		return $request;
	}

	/**
	 * Recursively map an array.
	 *
	 * @param callable $callback The sanitize_text_field function.
	 * @param array    $data     The nested array.
	 *
	 * @return array A new appearance array.
	 */
	private static function array_map_recursive( $callback, $data ) {
		$func = function ( $item ) use ( &$func, &$callback ) {
			return is_array( $item ) ? array_map( $func, $item ) : call_user_func( $callback, $item );
		};

		return array_map( $func, $data );
	}

	/**
	 * Sanitize a string.
	 *
	 * @param string $item A string.
	 *
	 * @return string The sanitized string.
	 */
	private static function sanitize_string( $item ) {
		return sanitize_text_field( wp_unslash( $item ) );
	}

	/**
	 * Sanitize font rules from the client.
	 *
	 * Font rules are an array of external font CDN stylesheet references sent alongside
	 * the WooPay appearance. Each rule is an associative array with a single key:
	 *
	 *   [ 'cssSrc' => 'https://fonts.googleapis.com/css2?family=...' ]
	 *
	 * Validation:
	 * - Each entry must have a string `cssSrc` key.
	 * - The URL must use HTTPS (enforced via esc_url_raw).
	 * - The host must be in WC_Payments_Styles_Cache::ALLOWED_FONT_DOMAINS.
	 * - Capped at 10 entries to prevent payload abuse.
	 *
	 * @param array $raw_rules Raw font rules array from the client.
	 * @return array Sanitized font rules, each as [ 'cssSrc' => string ].
	 */
	private static function sanitize_font_rules( $raw_rules ): array {
		if ( ! is_array( $raw_rules ) ) {
			return [];
		}

		$sanitized = [];
		foreach ( array_slice( $raw_rules, 0, 10 ) as $rule ) {
			if ( ! isset( $rule['cssSrc'] ) || ! is_string( $rule['cssSrc'] ) ) {
				continue;
			}
			$url  = esc_url_raw( $rule['cssSrc'], [ 'https' ] );
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $host && in_array( $host, \WC_Payments_Styles_Cache::ALLOWED_FONT_DOMAINS, true ) ) {
				$sanitized[] = [ 'cssSrc' => $url ];
			}
		}
		return $sanitized;
	}

	/**
	 * Used to initialize woopay session.
	 *
	 * @return void
	 */
	public static function ajax_init_woopay() {
		$is_nonce_valid = check_ajax_referer( 'wcpay_init_woopay_nonce', false, false );

		if ( ! $is_nonce_valid ) {
			wp_send_json_error(
				__( 'You aren’t authorized to do that.', 'woocommerce-payments' ),
				403
			);
		}

		$order_id      = ! empty( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : null;
		$key           = ! empty( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : null;
		$billing_email = ! empty( $_POST['billing_email'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_email'] ) ) : null;
		$appearance    = ! empty( $_POST['appearance'] ) ? self::array_map_recursive( array( __CLASS__, 'sanitize_string' ), $_POST['appearance'] ) : null; // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, Generic.Arrays.DisallowLongArraySyntax.Found
		$font_rules    = ! empty( $_POST['font_rules'] ) ? self::sanitize_font_rules( wp_unslash( $_POST['font_rules'] ) ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by sanitize_font_rules.

		$body                 = self::get_init_session_request( $order_id, $key, $billing_email, null, $appearance, $font_rules );
		$body['user_session'] = isset( $_REQUEST['user_session'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['user_session'] ) ) : null;

		$args = [
			'url'     => WooPay_Utilities::get_woopay_rest_url( 'init' ),
			'method'  => 'POST',
			'timeout' => 30,
			'body'    => wp_json_encode( $body ),
			'headers' => [
				'Content-Type' => 'application/json',
			],
		];

		$response = \Automattic\Jetpack\Connection\Client::remote_request( $args, wp_json_encode( $body ) );

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			Logger::error( 'HTTP_REQUEST_ERROR ' . var_export( $response, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			// Respond with same message platform would respond with on failure.
			$response_body_json = wp_json_encode( [ 'result' => 'failure' ] );
		} else {
			$response_body_json = wp_remote_retrieve_body( $response );
		}

		Logger::log( $response_body_json );
		wp_send_json( json_decode( $response_body_json ) );
	}

	/**
	 * Used to initialize woopay session on frontend
	 *
	 * @return void
	 */
	public static function ajax_get_woopay_session() {
		$is_nonce_valid = check_ajax_referer( 'woopay_session_nonce', false, false );

		if ( ! $is_nonce_valid ) {
			wp_send_json_error(
				__( 'You aren’t authorized to do that.', 'woocommerce-payments' ),
				403
			);
		}

		$blog_id = Jetpack_Options::get_option( 'id' );
		if ( empty( $blog_id ) ) {
			wp_send_json_error(
				__( 'Could not determine the blog ID.', 'woocommerce-payments' ),
				503
			);
		}

		wp_send_json( self::get_frontend_init_session_request() );
	}

	/**
	 * Save the blocks checkout phone number in session.
	 *
	 * @return void
	 */
	public static function ajax_set_woopay_phone_number() {
		$is_nonce_valid = check_ajax_referer( 'woopay_session_nonce', false, false );

		if ( ! $is_nonce_valid ) {
			wp_send_json_error(
				__( 'You aren’t authorized to do that.', 'woocommerce-payments' ),
				403
			);
		}

		if ( ! ( isset( WC()->session ) && WC()->session->has_session() ) ) {
			WC()->session->set_customer_session_cookie( true );
		}

		if ( ! empty( $_POST['empty'] ) && filter_var( wp_unslash( $_POST['empty'] ), FILTER_VALIDATE_BOOLEAN ) ) {
			WC()->session->__unset( self::WOOPAY_SESSION_KEY );

			wp_send_json_success();

			return;
		}

		$data = [
			'save_user_in_woopay'     => filter_var( wp_unslash( $_POST['save_user_in_woopay'] ), FILTER_VALIDATE_BOOLEAN ),
			'woopay_source_url'       =>
			wc_clean( wp_unslash( $_POST['woopay_source_url'] ) ),
			'woopay_is_blocks'        => filter_var( wp_unslash( $_POST['woopay_is_blocks'] ), FILTER_VALIDATE_BOOLEAN ),
			'woopay_viewport'         => wc_clean( wp_unslash( $_POST['woopay_viewport'] ) ),
			'woopay_user_phone_field' => [
				'full' => wc_clean( wp_unslash( $_POST['woopay_user_phone_field']['full'] ) ),
			],
		];

		WC()->session->set( self::WOOPAY_SESSION_KEY, $data );

		wp_send_json_success();
	}

	/**
	 * Used to initialize woopay session on frontend
	 *
	 * @return void
	 */
	public static function ajax_get_woopay_minimum_session_data() {
		$is_nonce_valid = check_ajax_referer( 'woopay_session_nonce', false, false );

		if ( ! $is_nonce_valid ) {
			wp_send_json_error(
				__( 'You aren’t authorized to do that.', 'woocommerce-payments' ),
				403
			);
		}

		$blog_id = Jetpack_Options::get_option( 'id' );
		if ( empty( $blog_id ) ) {
			wp_send_json_error(
				__( 'Could not determine the blog ID.', 'woocommerce-payments' ),
				503
			);
		}

		wp_send_json( self::get_woopay_minimum_session_data() );
	}

	/**
	 * Return WooPay minimum session data.
	 *
	 * @return array Array of minimum session data used by WooPay or false on failures.
	 */
	public static function get_woopay_minimum_session_data() {
		if ( ! extension_loaded( 'openssl' ) || ! function_exists( 'openssl_encrypt' ) ) {
			return [];
		}

		$blog_id = Jetpack_Options::get_option( 'id' );
		if ( empty( $blog_id ) ) {
			return [];
		}

		$data = [
			'wcpay_version'     => WCPAY_VERSION_NUMBER,
			'blog_id'           => $blog_id,
			'blog_rest_url'     => get_rest_url(),
			'blog_checkout_url' => wc_get_checkout_url(),
			'session_nonce'     => self::create_woopay_nonce( get_current_user_id() ),
			'store_api_token'   => self::init_store_api_token(),
		];

		return WooPay_Utilities::encrypt_and_sign_data( $data );
	}

	/**
	 * Returns true if the request that's currently being processed is from WooPay, false
	 * otherwise.
	 *
	 * @return bool True if request is from WooPay.
	 */
	public static function is_request_from_woopay(): bool {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) && 'WooPay' === $_SERVER['HTTP_USER_AGENT'];
	}

	/**
	 * Determines how the current request authenticated itself.
	 *
	 * A Cart-Token proves the caller holds that cart, which any shopper legitimately does
	 * for their own. It is therefore enough for proxied Store API traffic and not enough
	 * for anything that grants authority over an account — those paths ask for a
	 * store-minted nonce or an attestation on top of it. See WOOPAY-463.
	 *
	 * @return string One of the AUTH_* constants.
	 */
	public static function get_request_auth_level(): string {
		if ( null !== self::get_payload_from_cart_token() ) {
			return self::AUTH_CART_TOKEN;
		}

		return self::AUTH_NONE;
	}

	/**
	 * Returns the payload WooPay attested to on this request, or null if it did not.
	 *
	 * WooPay proves it composed this payload by encrypting it under the store blog token
	 * (`WooPay_Utilities::decrypt_signed_data()`) rather than by signing the HTTP request
	 * with it. The distinction matters: a signature authenticates the sender of whatever
	 * request it is attached to, while an encrypted envelope is bound to its own contents
	 * and confers nothing beyond them. See WOOPAY-463.
	 *
	 * A fresh timestamp is required, since the envelope carries no nonce of its own and
	 * would otherwise stay valid indefinitely. It is also spent on first use and refused
	 * thereafter. See `claim_attestation()`.
	 *
	 * Carried under its own parameter, and normally in a POST body so it stays out of
	 * access logs, browser history and Referer headers. Deliberately not the
	 * `encrypted_data` key `get_user_email()` reads: that is an older exchange with a
	 * different shape and different rules, and sharing a name would invite one to be
	 * mistaken for the other.
	 *
	 * @return array|null The attested payload, or null when absent, malformed, stale, or spent.
	 */
	public static function get_woopay_attestation(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification
		$envelope = $_POST[ self::ATTESTATION_PARAM ] ?? $_GET[ self::ATTESTATION_PARAM ] ?? null; // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! is_array( $envelope ) ) {
			// Carrying no envelope is ordinary — proxied Store API traffic never does — so
			// only say something when one was presented and could not be used.
			if ( null !== $envelope ) {
				Logger::log( 'WooPay attestation rejected: ' . self::ATTESTATION_PARAM . ' is not an envelope.' );
			}

			return null;
		}

		$parts = [];

		// decrypt_signed_data() indexes these directly, so reject anything malformed here
		// rather than warning on an undefined index inside it.
		foreach ( [ 'data', 'iv', 'hash' ] as $key ) {
			if ( ! isset( $envelope[ $key ] ) || ! is_string( $envelope[ $key ] ) ) {
				Logger::log( 'WooPay attestation rejected: envelope has no usable "' . $key . '" field.' );

				return null;
			}

			$parts[ $key ] = sanitize_text_field( wp_unslash( $envelope[ $key ] ) );
		}

		// The HMAC covers the IV and the ciphertext, and WooPay seals each envelope under a
		// fresh IV, so it identifies this one envelope and nothing else.
		$fingerprint = md5( $parts['hash'] );

		if ( array_key_exists( $fingerprint, self::$resolved_attestations ) ) {
			return self::$resolved_attestations[ $fingerprint ];
		}

		$decrypted = WooPay_Utilities::decrypt_signed_data( $parts );

		if ( ! is_array( $decrypted ) || ! isset( $decrypted['timestamp'] ) ) {
			// Distinguished from the other rejections because a bad rollout looks like this
			// from here, and is otherwise indistinguishable from a forgery. What to check
			// when it happens belongs with the sender, not in a public log line.
			Logger::log( 'WooPay attestation rejected: envelope did not open, or carries no timestamp.' );

			return null;
		}

		if ( ! is_numeric( $decrypted['timestamp'] ) || abs( time() - (int) $decrypted['timestamp'] ) > self::ATTESTATION_MAX_AGE ) {
			Logger::log( 'WooPay attestation rejected: envelope timestamp is missing or stale.' );

			return null;
		}

		if ( ! self::claim_attestation( $fingerprint ) ) {
			Logger::log( 'WooPay attestation rejected: envelope has already been used, or its claim could not be recorded.' );

			self::$resolved_attestations[ $fingerprint ] = null;

			return null;
		}

		self::$resolved_attestations[ $fingerprint ] = $decrypted;

		return $decrypted;
	}

	/**
	 * Returns the WooPay account email this request was sealed for, or null if it named none.
	 *
	 * Not to be confused with the `X-WooPay-Verified-Email-Address` header, which is a
	 * shopper's claim to an address they proved on WooPay and reaches the store unsealed.
	 * This is WooPay stating who the shopper is, and it is the account's own email rather
	 * than anything the shopper typed at checkout.
	 *
	 * An attestation need not name one — a guest shopper has no account — so this being null
	 * does not mean the request is unattested. Use `get_woopay_attestation()` for that.
	 *
	 * Deliberately stricter than the `encrypted_data` branch in `get_user_email()`, which
	 * accepts an envelope with no freshness check. Prefer this wherever the email decides
	 * what the request may do, such as which user a nonce is minted for.
	 *
	 * @return string|null The attested account email, or null when the attestation names none.
	 */
	public static function get_woopay_attested_account_email(): ?string {
		$attestation = self::get_woopay_attestation();

		if ( null === $attestation || empty( $attestation['user_email'] ) ) {
			return null;
		}

		$email = sanitize_email( $attestation['user_email'] );

		return is_email( $email ) ? $email : null;
	}

	/**
	 * Whether WooPay vouched for this email.
	 *
	 * Gates anything that grants authority over the account behind an email — chiefly
	 * minting `email_verified_session_nonce`, which authorizes promoting a guest session
	 * to that user. The email must therefore come from an authenticated source: do not
	 * relax this to `get_user_email()`, which also accepts plain request parameters.
	 *
	 * The attestation envelope is the only thing that vouches for one. See WOOPAY-463.
	 *
	 * @param string $email The email to check.
	 *
	 * @return bool True if WooPay attested to this email.
	 */
	public static function is_email_attested_by_woopay( string $email ): bool {
		$attested_email = self::get_woopay_attested_account_email();

		return null !== $attested_email && 0 === strcasecmp( $attested_email, $email );
	}

	/**
	 * Says why the current request could not be authenticated.
	 *
	 * Rejecting is not negotiable, so the useful thing is to name the cause rather than fail
	 * both the same way. Each maps to a distinct code, in the log and in the response body,
	 * so a store owner and WooPay can tell them apart:
	 *
	 * - `woopay_invalid_cart_token`: a Cart-Token arrived but does not validate. Either it
	 *   expired mid-checkout, or the two ends disagree about `wp_salt()`.
	 * - `woopay_request_not_authenticated`: nothing was presented at all.
	 *
	 * @return \WP_Error The error to end the request with.
	 */
	private static function get_unauthenticated_request_error(): \WP_Error {
		if ( isset( $_SERVER['HTTP_CART_TOKEN'] ) ) {
			return new \WP_Error(
				'woopay_invalid_cart_token',
				__( 'The Cart-Token on this WooPay request is invalid or expired.', 'woocommerce-payments' ),
				[ 'status' => 401 ]
			);
		}

		return new \WP_Error(
			'woopay_request_not_authenticated',
			__( 'WooPay request is not signed correctly.', 'woocommerce-payments' ),
			[ 'status' => 401 ]
		);
	}

	/**
	 * Spends an attestation envelope, returning false if it was already spent.
	 *
	 * Freshness alone leaves a captured envelope replayable for the whole window, and
	 * replaying one is not merely a session read: it mints `email_verified_session_nonce`
	 * for whichever user the envelope names, which is enough to resolve as that user on
	 * the Store API. Spending it on arrival is what keeps a captured envelope worthless.
	 * The body keeps it out of logs and history; this covers whatever saw the request
	 * itself. See WOOPAY-463.
	 *
	 * The claim outlives the freshness window on both sides, since `ATTESTATION_MAX_AGE`
	 * is applied to the absolute clock difference and so also admits envelopes dated
	 * slightly ahead of the store.
	 *
	 * The write decides, not the read before it. `add_option()` underneath refuses a key
	 * that already exists, so of two requests arriving with the same envelope at once —
	 * both past the `get_transient()` check — only one gets a true back. Trusting the read
	 * would let both through, which is the replay this exists to stop. It also fails
	 * closed if the claim cannot be recorded at all: an envelope whose guard is not in
	 * place is refused rather than accepted on the assumption it will be.
	 *
	 * @param string $fingerprint Fingerprint of the envelope being spent.
	 *
	 * @return bool True if this call spent the envelope, false if it was already spent or
	 *              the claim could not be recorded.
	 */
	private static function claim_attestation( string $fingerprint ): bool {
		$key = self::ATTESTATION_CLAIM_PREFIX . $fingerprint;

		if ( false !== get_transient( $key ) ) {
			return false;
		}

		return (bool) set_transient( $key, time(), 2 * self::ATTESTATION_MAX_AGE );
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
	 * Returns true if WooPay is enabled, false otherwise.
	 *
	 * @return bool True if WooPay is enabled, false otherwise.
	 */
	private static function is_woopay_enabled(): bool {
		// There were previously instances of this function being called too early. While those should be resolved, adding this defensive check as well.
		if ( ! class_exists( WC_Payments_Features::class ) || ! class_exists( WC_Payments::class ) || is_null( WC_Payments::get_gateway() ) ) {
			return false;
		}

		return WC_Payments_Features::is_woopay_eligible() && 'yes' === WC_Payments::get_gateway()->get_option( 'platform_checkout', 'no' );
	}

	/**
	 * Initializes the WooPay_Store_Api_Token class and returns the Cart token.
	 *
	 * @return string The Cart Token.
	 */
	private static function init_store_api_token() {
		$cart_route = WooPay_Store_Api_Token::init();

		return $cart_route->get_cart_token();
	}

	/**
	 * Retrieves the Store API URL.
	 *
	 * @return string
	 */
	private static function get_store_api_url() {
		if ( class_exists( StoreApi::class ) && class_exists( RoutesController::class ) ) {
			try {
				$cart          = StoreApi::container()->get( RoutesController::class )->get( 'cart' );
				$store_api_url = method_exists( $cart, 'get_namespace' ) ? $cart->get_namespace() : 'wc/store';
			} catch ( \Exception $e ) {
				$store_api_url = 'wc/store';
			}
		}

		return get_rest_url( null, $store_api_url ?? 'wc/store' );
	}

	/**
	 * WooPay requests to the merchant API does not include a cookie, so the token
	 * is always empty. This function creates a nonce that can be used without
	 * a cookie.
	 *
	 * @param int $uid The uid to be used for the nonce. Most likely the user ID.
	 * @return false|string
	 */
	private static function create_woopay_nonce( int $uid ) {
		$action = 'wc_store_api';
		$token  = '';
		$i      = wp_nonce_tick( $action );

		return substr( wp_hash( $i . '|' . $action . '|' . $uid . '|' . $token, 'nonce' ), -12, 10 );
	}

	/**
	 * Verifies a nonce minted by create_woopay_nonce() against a specific user ID.
	 *
	 * Mirrors wp_verify_nonce()'s two-tick tolerance, but pins the user ID instead of
	 * reading it from the current session — the point is to prove the store itself
	 * issued this nonce for this user.
	 *
	 * Note the Store API's own nonce check does not do this for us: requires_nonce() in
	 * AbstractCartRoute skips verification entirely whenever a valid Cart-Token is
	 * present, which is always the case for WooPay traffic.
	 *
	 * @param string $nonce The nonce to verify.
	 * @param int    $uid   The user ID the nonce must be bound to.
	 *
	 * @return bool True if the nonce was issued by this store for this user.
	 */
	private static function verify_woopay_nonce( string $nonce, int $uid ): bool {
		$action = 'wc_store_api';
		$token  = '';
		$i      = wp_nonce_tick( $action );

		foreach ( [ $i, $i - 1 ] as $tick ) {
			$expected = substr( wp_hash( $tick . '|' . $action . '|' . $uid . '|' . $token, 'nonce' ), -12, 10 );

			if ( hash_equals( $expected, $nonce ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the request carries a store-minted nonce bound to the given user ID.
	 *
	 * @param int $uid The user ID the nonce must be bound to.
	 *
	 * @return bool True if the Nonce header is valid for that user.
	 */
	private static function has_store_minted_nonce_for_user( int $uid ): bool {
		$nonce = isset( $_SERVER['HTTP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_NONCE'] ) ) : '';

		if ( '' === $nonce ) {
			return false;
		}

		return self::verify_woopay_nonce( $nonce, $uid );
	}

	/**
	 * Gets the custom message from the settings and replaces the placeholders with the correct links.
	 *
	 * @return string The custom message with the placeholders replaced.
	 */
	private static function get_formatted_custom_terms() {
		$custom_message = WC_Payments::get_gateway()->get_option( 'platform_checkout_custom_message' );

		$terms_value          = wc_terms_and_conditions_page_id() ?
			'<a href="' . get_permalink( wc_terms_and_conditions_page_id() ) . '">' . __( 'Terms of Service', 'woocommerce-payments' ) . '</a>' :
			__( 'Terms of Service', 'woocommerce-payments' );
		$privacy_policy_value = wc_privacy_policy_page_id() ?
			'<a href="' . get_permalink( wc_privacy_policy_page_id() ) . '">' . __( 'Privacy Policy', 'woocommerce-payments' ) . '</a>' :
			__( 'Privacy Policy', 'woocommerce-payments' );

		$replacement_map = [
			'[terms_of_service_link]' => $terms_value,
			'[terms]'                 => $terms_value,
			'[privacy_policy_link]'   => $privacy_policy_value,
			'[privacy_policy]'        => $privacy_policy_value,
		];

		return str_replace( array_keys( $replacement_map ), array_values( $replacement_map ), $custom_message );
	}

	/**
	 * Returns the status of checkout optional/required address fields.
	 *
	 * @return array The status of the checkout fields.
	 */
	private static function get_option_fields_status() {
		// Shortcode checkout options.
		$company                              = get_option( 'woocommerce_checkout_company_field', 'optional' );
		$address_2                            = get_option( 'woocommerce_checkout_address_2_field', 'optional' );
		$phone                                = get_option( 'woocommerce_checkout_phone_field', 'required' );
		$has_terms_and_condition_page         = ! empty( get_option( 'woocommerce_terms_page_id', null ) );
		$terms_and_conditions                 = wp_kses_post( wc_replace_policy_page_link_placeholders( wc_get_terms_and_conditions_checkbox_text() ) );
		$has_privacy_policy_page              = ! empty( get_option( 'wp_page_for_privacy_policy', null ) );
		$custom_below_place_order_button_text = self::get_formatted_custom_terms();
		$below_place_order_button_text        = $custom_below_place_order_button_text;
		$show_terms_checkbox                  = false;

		// Blocks checkout options. To get the blocks checkout options, we need
		// to parse the checkout page content because the options are stored
		// in the blocks HTML as a JSON.
		$checkout_page_id = get_option( 'woocommerce_checkout_page_id' );
		$checkout_page    = get_post( $checkout_page_id );

		/*
		 * Will show the terms checkbox if the terms page is set.
		 * Will show the checkbox even when the text is loaded from the custom field or the policy page field.
		 */
		if ( $has_terms_and_condition_page && $terms_and_conditions ) {
			$show_terms_checkbox = true;
			if ( ! $below_place_order_button_text ) {
				$below_place_order_button_text = $terms_and_conditions;
			}
		}

		if ( ! $below_place_order_button_text && $has_privacy_policy_page ) {
			$show_terms_checkbox           = false;
			$below_place_order_button_text = wp_kses_post( wc_replace_policy_page_link_placeholders( wc_get_privacy_policy_text( 'checkout' ) ) );
		}

		if ( empty( $checkout_page ) ) {
			return [
				'company'        => $company,
				'address_2'      => $address_2,
				'phone'          => $phone,
				'terms_checkbox' => $show_terms_checkbox,
				'custom_terms'   => $below_place_order_button_text,
			];
		}

		$checkout_page_blocks = parse_blocks( $checkout_page->post_content );
		$checkout_block_index = array_search( 'woocommerce/checkout', array_column( $checkout_page_blocks, 'blockName' ), true );

		// If we can find the index, it means the merchant checkout page is using blocks checkout.
		if ( false !== $checkout_block_index ) {
			$below_place_order_button_text = $custom_below_place_order_button_text;
			$company                       = 'optional';
			$address_2                     = 'optional';
			$phone                         = 'optional';

			if ( ! empty( $checkout_page_blocks[ $checkout_block_index ]['attrs'] ) ) {
				$checkout_block_attrs = $checkout_page_blocks[ $checkout_block_index ]['attrs'];

				if ( ! empty( $checkout_block_attrs['requireCompanyField'] ) ) {
					$company = 'required';
				}

				if ( ! empty( $checkout_block_attrs['requirePhoneField'] ) ) {
					$phone = 'required';
				}

				// showCompanyField is undefined by default.
				if ( empty( $checkout_block_attrs['showCompanyField'] ) ) {
					$company = 'hidden';
				}

				if ( isset( $checkout_block_attrs['showApartmentField'] ) && false === $checkout_block_attrs['showApartmentField'] ) {
					$address_2 = 'hidden';
				}

				if ( isset( $checkout_block_attrs['showPhoneField'] ) && false === $checkout_block_attrs['showPhoneField'] ) {
					$phone = 'hidden';
				}
			}

			$fields_block                  = self::get_inner_block( $checkout_page_blocks[ $checkout_block_index ], 'woocommerce/checkout-fields-block' );
			$terms_block                   = self::get_inner_block( $fields_block, 'woocommerce/checkout-terms-block' );
			$show_terms_checkbox           = false;
			$below_place_order_button_text = '';

			if ( $terms_block ) {
				$show_terms_checkbox           = isset( $terms_block['attrs']['checkbox'] ) && $terms_block['attrs']['checkbox'];
				$below_place_order_button_text = self::get_blocks_terms_and_conditions_text( $terms_block, $show_terms_checkbox );
			}
		}

		return [
			'company'        => $company,
			'address_2'      => $address_2,
			'phone'          => $phone,
			'terms_checkbox' => $show_terms_checkbox,
			'custom_terms'   => $below_place_order_button_text,
		];
	}

	/**
	 * Gets the blocks terms and conditions text.
	 *
	 * @param array $terms_block the terms block.
	 * @param bool  $show_terms_checkbox whether the terms checkbox is shown.
	 * @return string
	 */
	private static function get_blocks_terms_and_conditions_text( $terms_block, $show_terms_checkbox ) {
		if ( isset( $terms_block['attrs']['text'] ) && ! empty( $terms_block['attrs']['text'] ) ) {
			return $terms_block['attrs']['text'];
		}

		$privacy_page_link = get_privacy_policy_url();
		$privacy_page_link = $privacy_page_link ? '<a href="' . $privacy_page_link . '" target="_blank">' . __( 'Privacy Policy', 'woocommerce-payments' ) . '</a>' : __( 'Privacy Policy', 'woocommerce-payments' );

		$terms_page_id   = wc_terms_and_conditions_page_id();
		$terms_page_link = '';
		if ( $terms_page_id ) {
			$terms_page_link = get_permalink( $terms_page_id );
		}

		$terms_page_link = $terms_page_link ? '<a href="' . $terms_page_link . '" target="_blank">' . __( 'Terms and Conditions', 'woocommerce-payments' ) . '</a>' : __( 'Terms and Conditions', 'woocommerce-payments' );

		if ( $show_terms_checkbox ) {
			return sprintf(
			/* translators: %1$s terms page link, %2$s privacy page link. */
				__( 'You must accept our %1$s and %2$s to continue with your purchase.', 'woocommerce-payments' ),
				$terms_page_link,
				$privacy_page_link
			);
		}

		return sprintf(
			/* translators: %1$s terms page link, %2$s privacy page link. */
			__( 'By proceeding with your purchase you agree to our %1$s and %2$s', 'woocommerce-payments' ),
			$terms_page_link,
			$privacy_page_link
		);
	}

	/**
	 * Searches for an inner block with the given name.
	 *
	 * @param array  $current_block A block that contains child blocks.
	 * @param string $inner_block_name The name of a child block.
	 * @return array|null
	 */
	private static function get_inner_block( $current_block, $inner_block_name ) {

		if ( ! isset( $current_block['innerBlocks'] ) ) {
			return;
		}

		$inner_block_index = array_search(
			$inner_block_name,
			array_column(
				$current_block['innerBlocks'],
				'blockName'
			),
			true
		);

		if ( ! $inner_block_index || ! isset( $current_block['innerBlocks'][ $inner_block_index ] ) ) {
			return;
		}

		return $current_block['innerBlocks'][ $inner_block_index ];
	}

	/**
	 * Catches and logs errors that occur during WooPay checkout processing.
	 * This is particularly important for third-party plugin compatibility issues
	 * (e.g., calling methods that don't exist on the WooPay SessionHandler).
	 *
	 * This method sets up an error handler that will catch PHP errors and add
	 * them as order notes for debugging purposes.
	 *
	 * @param \WC_Order $order The order being processed.
	 * @return void
	 */
	public static function catch_woopay_checkout_errors( $order ) {
		if ( ! self::is_request_from_woopay() || ! ( $order instanceof \WC_Order ) ) {
			return;
		}

		// Store the order ID so the error handler can access it.
		self::$checkout_error_order_id = $order->get_id();

		if ( self::$is_error_handler_registered ) {
			return;
		}

		// Register shutdown function to catch fatal errors and restore previous handler.
		register_shutdown_function(
			function () {
				$error = error_get_last();
				if ( ! $error || ! self::is_request_from_woopay() ) {
					return;
				}

				// Only handle fatal errors.
				if ( ! in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ], true ) ) {
					return;
				}

				// Log the fatal error.
				Logger::error(
					sprintf(
						'WooPay checkout fatal error: %s in %s on line %d',
						$error['message'],
						$error['file'],
						$error['line']
					)
				);

				// Add order note with the error details.
				if ( self::$checkout_error_order_id ) {
					$woopay_order = wc_get_order( self::$checkout_error_order_id );
					if ( $woopay_order ) {
						// Extract only the first line of the error message.
						$error_first_line = strtok( $error['message'], "\n" );
						$note             = sprintf(
							/* translators: %s: error message */
							__( 'WooPay checkout encountered a fatal error: %s', 'woocommerce-payments' ),
							esc_html( $error_first_line )
						);
						$woopay_order->add_order_note( $note );
					}
				}
			}
		);

		self::$is_error_handler_registered = true;
	}

	/**
	 * AJAX handler: admin stores the WooPay checkout appearance.
	 *
	 * Requires manage_woocommerce capability. Always accepts the write,
	 * overwriting any existing value. Used from the checkout customizer.
	 *
	 * @return void
	 */
	public static function ajax_admin_set_woopay_appearance() {
		check_ajax_referer( 'wcpay_admin_woopay_appearance_nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				__( 'You aren\'t authorized to do that.', 'woocommerce-payments' ),
				403
			);
		}

		if ( ! \WC_Payments::get_gateway()->is_woopay_global_theme_support_enabled() ) {
			wp_send_json_error(
				__( 'This action is not available.', 'woocommerce-payments' ),
				403
			);
		}

		if ( empty( $_POST['appearance'] ) || ! is_array( $_POST['appearance'] ) ) {
			wp_send_json_error(
				__( 'Missing or invalid appearance data.', 'woocommerce-payments' ),
				400
			);
		}

		$appearance = self::array_map_recursive( [ __CLASS__, 'sanitize_string' ], $_POST['appearance'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! \WC_Payments_Styles_Cache::validate_appearance_schema( $appearance ) ) {
			wp_send_json_error(
				__( 'Invalid appearance schema.', 'woocommerce-payments' ),
				400
			);
		}

		$font_rules = [];
		if ( ! empty( $_POST['font_rules'] ) ) {
			$raw_font_rules = json_decode( wp_unslash( $_POST['font_rules'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded values are sanitized by sanitize_font_rules().
			$font_rules     = is_array( $raw_font_rules ) ? self::sanitize_font_rules( $raw_font_rules ) : [];
		}

		\WC_Payments_Styles_Cache::set_woopay_appearance( $appearance, $font_rules );

		wp_send_json_success();
	}

	/**
	 * AJAX handler: shopper conditionally stores the WooPay checkout appearance.
	 *
	 * Only accepts the write if no valid appearance exists for the current
	 * styles cache version. Once the slot is filled (by admin or first shopper),
	 * subsequent writes are rejected until the next theme change.
	 *
	 * @return void
	 */
	public static function ajax_shopper_set_woopay_appearance() {
		$is_nonce_valid = check_ajax_referer( 'woopay_session_nonce', false, false );

		if ( ! $is_nonce_valid ) {
			wp_send_json_error(
				__( 'You aren\'t authorized to do that.', 'woocommerce-payments' ),
				403
			);
		}

		if ( ! \WC_Payments::get_gateway()->is_woopay_global_theme_support_enabled() ) {
			wp_send_json_error(
				__( 'This action is not available.', 'woocommerce-payments' ),
				403
			);
		}

		if ( empty( $_POST['appearance'] ) || ! is_array( $_POST['appearance'] ) ) {
			wp_send_json_error(
				__( 'Missing or invalid appearance data.', 'woocommerce-payments' ),
				400
			);
		}

		$appearance = self::array_map_recursive( [ __CLASS__, 'sanitize_string' ], $_POST['appearance'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! \WC_Payments_Styles_Cache::validate_appearance_schema( $appearance ) ) {
			wp_send_json_error(
				__( 'Invalid appearance schema.', 'woocommerce-payments' ),
				400
			);
		}

		$font_rules = [];
		if ( ! empty( $_POST['font_rules'] ) ) {
			$raw_font_rules = json_decode( wp_unslash( $_POST['font_rules'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded values are sanitized by sanitize_font_rules().
			$font_rules     = is_array( $raw_font_rules ) ? self::sanitize_font_rules( $raw_font_rules ) : [];
		}

		$stored = \WC_Payments_Styles_Cache::maybe_set_woopay_appearance( $appearance, $font_rules );

		if ( ! $stored ) {
			wp_send_json_success( [ 'stored' => false ] );
			return;
		}

		wp_send_json_success( [ 'stored' => true ] );
	}
}
