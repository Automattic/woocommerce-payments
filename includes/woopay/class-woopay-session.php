<?php
/**
 * Class WooPay_Session.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\WooPay;

use Automattic\Jetpack\Connection\Rest_Authentication;
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
		'@^\/wc\/store(\/v[\d]+)?\/checkout\/(?P<id>[\d]+)@',
		'@^\/wc\/store(\/v[\d]+)?\/checkout$@',
		'@^\/wc\/store(\/v[\d]+)?\/order\/(?P<id>[\d]+)@',
	];

	/**
	 * Init the hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'determine_current_user', [ __CLASS__, 'determine_current_user_for_woopay' ], 20 );
		add_filter( 'woocommerce_session_handler', [ __CLASS__, 'add_woopay_store_api_session_handler' ], 20 );
		add_action( 'woocommerce_order_payment_status_changed', [ __CLASS__, 'remove_order_customer_id_on_requests_with_verified_email' ] );
		add_action( 'woopay_restore_order_customer_id', [ __CLASS__, 'restore_order_customer_id_from_requests_with_verified_email' ] );

		register_deactivation_hook( WCPAY_PLUGIN_FILE, [ __CLASS__, 'run_and_remove_woopay_restore_order_customer_id_schedules' ] );
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
			self::is_store_api_request() &&
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
		if ( (is_countable($enabled_adapted_extensions) ? count( $enabled_adapted_extensions ) : 0) > 0 && null !== $woopay_verified_email_address && ! empty( $customer['email'] ) ) {
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

		if ( (is_countable($enabled_adapted_extensions) ? count( $enabled_adapted_extensions ) : 0) === 0 ) {
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
	 * Returns the encrypted session request for the frontend.
	 *
	 * @return array The encrypted session request or an empty array if the server is not eligible for encryption.
	 */
	public static function get_frontend_init_session_request() {
		if ( ! WC_Payments_Features::is_client_secret_encryption_eligible() ) {
			return [];
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$order_id      = ! empty( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : null;
		$key           = ! empty( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : null;
		$billing_email = ! empty( $_POST['billing_email'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_email'] ) ) : null;
		// phpcs:enable

		$session = self::get_init_session_request( $order_id, $key, $billing_email );

		$store_blog_token = ( WooPay_Utilities::get_woopay_url() === WooPay_Utilities::DEFAULT_WOOPAY_URL ) ? Jetpack_Options::get_option( 'blog_token' ) : 'dev_mode';

		$message = wp_json_encode( $session );

		// Generate an initialization vector (IV) for encryption.
		$iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );

		// Encrypt the JSON session.
		$session_encrypted = openssl_encrypt( $message, 'aes-256-cbc', $store_blog_token, OPENSSL_RAW_DATA, $iv );

		// Create an HMAC hash for data integrity.
		$hash = hash_hmac( 'sha256', $session_encrypted, $store_blog_token );

		$data = [
			'session' => $session_encrypted,
			'iv'      => $iv,
			'hash'    => $hash,
		];

		$response = [
			'blog_id' => Jetpack_Options::get_option( 'id' ),
			'data'    => array_map( 'base64_encode', $data ),
		];

		return $response;
	}

	/**
	 * Returns the initial session request data.
	 *
	 * @param int|null    $order_id Pay-for-order order ID.
	 * @param string|null $key Pay-for-order key.
	 * @param string|null $billing_email Pay-for-order billing email.
	 * @return array The initial session request data without email and user_session.
	 */
	private static function get_init_session_request( $order_id = null, $key = null, $billing_email = null ) {
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

		// This uses the same logic as the Checkout block in hydrate_from_api to get the cart and checkout data.
		$cart_data = ! $is_pay_for_order
			? rest_preload_api_request( [], '/wc/store/v1/cart' )['/wc/store/v1/cart']['body']
			: rest_preload_api_request( [], "/wc/store/v1/order/{$order_id}?key={$key}&billing_email={$billing_email}" )[ "/wc/store/v1/order/{$order_id}?key={$key}&billing_email={$billing_email}" ]['body'];
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
		$preloaded_checkout_data = rest_preload_api_request( [], '/wc/store/v1/checkout' );
		remove_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
		$checkout_data = isset( $preloaded_checkout_data['/wc/store/v1/checkout'] ) ? $preloaded_checkout_data['/wc/store/v1/checkout']['body'] : '';

		$request = [
			'wcpay_version'        => WCPAY_VERSION_NUMBER,
			'user_id'              => $user->ID,
			'customer_id'          => $customer_id,
			'session_nonce'        => self::create_woopay_nonce( $user->ID ),
			'store_api_token'      => self::init_store_api_token(),
			'email'                => '',
			'store_data'           => [
				'store_name'                     => get_bloginfo( 'name' ),
				'store_logo'                     => $store_logo,
				'custom_message'                 => self::get_formatted_custom_message(),
				'blog_id'                        => Jetpack_Options::get_option( 'id' ),
				'blog_url'                       => get_site_url(),
				'blog_checkout_url'              => ! $is_pay_for_order ? wc_get_checkout_url() : $order->get_checkout_payment_url(),
				'blog_shop_url'                  => get_permalink( wc_get_page_id( 'shop' ) ),
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
			'tracks_user_identity' => WC_Payments::woopay_tracker()->tracks_get_identity( $user->ID ),
		];

		return $request;
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

		$email         = ! empty( $_POST['email'] ) ? wc_clean( wp_unslash( $_POST['email'] ) ) : '';
		$order_id      = ! empty( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : null;
		$key           = ! empty( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : null;
		$billing_email = ! empty( $_POST['billing_email'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_email'] ) ) : null;

		$body                 = self::get_init_session_request( $order_id, $key, $billing_email );
		$body['email']        = $email;
		$body['user_session'] = isset( $_REQUEST['user_session'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['user_session'] ) ) : null;

		if ( ! empty( $email ) ) {
			// Save email in session to skip TYP verify email and check if
			// WooPay verified email matches.
			WC()->customer->set_billing_email( $email );
			WC()->customer->save();

			$woopay_adapted_extensions  = new WooPay_Adapted_Extensions();
			$body['adapted_extensions'] = $woopay_adapted_extensions->get_adapted_extensions_data( $email );

			if ( ! is_user_logged_in() && count( $body['adapted_extensions'] ) > 0 ) {
				$store_user_email_registered = get_user_by( 'email', $email );

				if ( $store_user_email_registered ) {
					$body['email_verified_session_nonce'] = self::create_woopay_nonce( $store_user_email_registered->ID );
				}
			}
		}

		$args = [
			'url'     => WooPay_Utilities::get_woopay_rest_url( 'init' ),
			'method'  => 'POST',
			'timeout' => 30,
			'body'    => wp_json_encode( $body ),
			'headers' => [
				'Content-Type' => 'application/json',
			],
		];

		/**
		 * Suppress psalm error from Jetpack Connection namespacing WP_Error.
		 *
		 * @psalm-suppress UndefinedDocblockClass
		 */
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

		wp_send_json( self::get_frontend_init_session_request() );
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
	 * Gets the custom message from the settings and replaces the placeholders with the correct links.
	 *
	 * @return string The custom message with the placeholders replaced.
	 */
	private static function get_formatted_custom_message() {
		$custom_message = WC_Payments::get_gateway()->get_option( 'platform_checkout_custom_message' );

		$replacement_map = [
			'[terms_of_service_link]' => wc_terms_and_conditions_page_id() ?
				'<a href="' . get_permalink( wc_terms_and_conditions_page_id() ) . '">' . __( 'Terms of Service', 'woocommerce-payments' ) . '</a>' :
				__( 'Terms of Service', 'woocommerce-payments' ),
			'[privacy_policy_link]'   => wc_privacy_policy_page_id() ?
				'<a href="' . get_permalink( wc_privacy_policy_page_id() ) . '">' . __( 'Privacy Policy', 'woocommerce-payments' ) . '</a>' :
				__( 'Privacy Policy', 'woocommerce-payments' ),
		];

		return str_replace( array_keys( $replacement_map ), array_values( $replacement_map ), $custom_message );
	}
}
