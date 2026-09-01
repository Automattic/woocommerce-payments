<?php
/**
 * WooPay
 *
 * @package WCPay\WooPay
 */

namespace WCPay\WooPay;

use WC_Payments_Features;
use WC_Payments_Subscriptions_Utilities;
use WCPay\Logger;
use WC_Geolocation;
use WC_Payments;
use Jetpack_Options;

/**
 * WooPay
 */
class WooPay_Utilities {
	use WC_Payments_Subscriptions_Utilities;

	const AVAILABLE_COUNTRIES_OPTION_NAME = 'woocommerce_woocommerce_payments_woopay_available_countries';
	const AVAILABLE_COUNTRIES_DEFAULT     = '["US"]';

	const DEFAULT_WOOPAY_URL = 'https://pay.woo.com';

	/**
	 * HKDF label for payloads this store seals and WooPay opens.
	 *
	 * Must match the label WooPay derives with. Versioned so the pair can be rotated
	 * without the two ends having to agree on a flag day.
	 */
	const SESSION_KEY_PURPOSE = 'woopay-session-v1';

	/**
	 * HKDF label for payloads WooPay seals and this store opens.
	 */
	const ATTESTATION_KEY_PURPOSE = 'woopay-attestation-v1';

	/**
	 * HKDF label for the envelope WooPay attaches to proxied checkout requests.
	 *
	 * Separate from the session envelope on purpose: the two travel differently and are
	 * governed by different rules. Deriving a key per use is what stops one being
	 * presented as the other.
	 */
	const VOUCH_KEY_PURPOSE = 'woopay-vouch-v1';

	/**
	 * HKDF label for the envelope WooPay's connect page seals for this store.
	 *
	 * Accepted but not yet sent. WooPay seals that exchange with the undifferentiated blog
	 * token and cannot stop until every store accepts a derived key, because the connect
	 * iframe serves stores on every release and nothing on that request tells it which one
	 * it is talking to. Accepting it here is the half that has to ship first, so the day
	 * WooPay switches, no store is left unable to open what it is sent.
	 */
	const CONNECT_KEY_PURPOSE = 'woopay-connect-v1';

	/**
	 * Check various conditions to determine if we should enable woopay.
	 *
	 * @param \WC_Payment_Gateway_WCPay $gateway Gateway instance.
	 * @return boolean
	 */
	public function should_enable_woopay( $gateway ) {
		$is_woopay_eligible = WC_Payments_Features::is_woopay_eligible(); // Feature flag.
		$is_woopay_enabled  = 'yes' === $gateway->get_option( 'platform_checkout', 'no' );

		return $is_woopay_eligible && $is_woopay_enabled;
	}

	/**
	 * Check conditions to determine if WooPay should be enabled for guest checkout.
	 *
	 * @return bool  True if WooPay should be enabled, false otherwise.
	 */
	public function should_enable_woopay_on_guest_checkout(): bool {
		if ( ! is_user_logged_in() ) {
			// If there's a subscription product in the cart and the customer isn't logged in we
			// should not enable WooPay since that situation is currently not supported.
			// Note that this is mirrored in the WC_Payments_WooPay_Button_Handler class.
			if ( class_exists( 'WC_Subscriptions_Cart' ) && \WC_Subscriptions_Cart::cart_contains_subscription() ) {
				return false;
			}

			// If guest checkout is disabled and the customer isn't logged in we should not enable
			// WooPay scripts since that situations is currently not supported.
			// Note that this is mirrored in the WC_Payments_WooPay_Button_Handler class.
			if ( ! $this->is_guest_checkout_enabled() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check conditions to determine if woopay express checkout is enabled.
	 *
	 * @return boolean
	 */
	public function is_woopay_express_checkout_enabled() {
		return WC_Payments_Features::is_woopay_express_checkout_enabled() && $this->is_country_available( WC_Payments::get_gateway() ); // Feature flag.
	}

	/**
	 * Check conditions to determine if woopay first party auth is enabled.
	 *
	 * @return bool
	 */
	public function is_woopay_first_party_auth_enabled() {
		return WC_Payments_Features::is_woopay_express_checkout_enabled() && $this->is_country_available( WC_Payments::get_gateway() ); // Feature flag.
	}

	/**
	 * Determines if the WooPay email input hooks should be enabled.
	 *
	 * This function doesn't affect the appearance of the email input,
	 * only whether or not the email exists check or auto-redirection should be enabled.
	 *
	 * @return bool
	 */
	public function is_woopay_email_input_enabled() {
		/**
		 * Filters whether the WooPay email input hooks should be enabled.
		 *
		 * @since 6.9.0
		 *
		 * @param bool $enabled Whether the WooPay email input behaviour is enabled.
		 */
		return apply_filters( 'wcpay_is_woopay_email_input_enabled', true );
	}

	/**
	 * Generates a hash based on the store's blog token, merchant ID, and the time step window.
	 *
	 * @return string
	 */
	public function get_woopay_request_signature() {
		$store_blog_token = \Jetpack_Options::get_option( 'blog_token' );
		$time_step_window = floor( time() / 30 );

		return hash_hmac( 'sha512', \Jetpack_Options::get_option( 'id' ) . $time_step_window, $store_blog_token );
	}

	/**
	 * Check session to determine if we should create a platform customer.
	 *
	 * @return boolean
	 */
	public function should_save_platform_customer() {
		$session_data = [];

		if ( isset( WC()->session ) && method_exists( WC()->session, 'has_session' ) && WC()->session->has_session() ) {
			$session_data = WC()->session->get( WooPay_Session::WOOPAY_SESSION_KEY );
		}

		$save_user_in_woopay_post    = isset( $_POST['save_user_in_woopay'] ) && filter_var( wp_unslash( $_POST['save_user_in_woopay'] ), FILTER_VALIDATE_BOOLEAN ); // phpcs:ignore WordPress.Security.NonceVerification
		$save_user_in_woopay_session = isset( $session_data['save_user_in_woopay'] ) && filter_var( $session_data['save_user_in_woopay'], FILTER_VALIDATE_BOOLEAN );

		return $save_user_in_woopay_post || $save_user_in_woopay_session;
	}

	/**
	 * Get if WooPay is available on the user country.
	 *
	 * @return boolean
	 */
	public function is_country_available() {
		if ( WC_Payments::mode()->is_test() ) {
			return true;
		}

		$location_data = WC_Geolocation::geolocate_ip();

		$available_countries = self::get_persisted_available_countries();

		return in_array( $location_data['country'], $available_countries, true );
	}

	/**
	 * Sanitizes an intent ID by stripping everything by underscores, characters and digits.
	 *
	 * @param string $intent_id ID of the intent.
	 * @return string Sanitized value.
	 */
	public static function sanitize_intent_id( string $intent_id ) {
		return preg_replace( '/[^\w_]+/', '', $intent_id );
	}

	/**
	 * Get if WooPay is available on the store country.
	 *
	 * @return boolean
	 */
	public static function is_store_country_available() {
		$store_base_location = wc_get_base_location();

		if ( empty( $store_base_location['country'] ) ) {
			return false;
		}

		$available_countries = self::get_persisted_available_countries();

		return in_array( $store_base_location['country'], $available_countries, true );
	}

	/**
	 * Get phone number for creating woopay customer.
	 *
	 * @return mixed|string
	 */
	public function get_woopay_phone() {
		$session_data = WC()->session->get( WooPay_Session::WOOPAY_SESSION_KEY );

		if ( ! empty( $_POST['woopay_user_phone_field']['full'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return wc_clean( wp_unslash( $_POST['woopay_user_phone_field']['full'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( ! empty( $session_data['woopay_user_phone_field']['full'] ) ) {
			return $session_data['woopay_user_phone_field']['full'];
		}

		return '';
	}

	/**
	 * Get the url marketing where the user have chosen marketing options.
	 *
	 * @return mixed|string
	 */
	public function get_woopay_source_url() {
		$session_data = WC()->session->get( WooPay_Session::WOOPAY_SESSION_KEY );

		if ( ! empty( $_POST['woopay_source_url'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return wc_clean( wp_unslash( $_POST['woopay_source_url'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( ! empty( $session_data['woopay_source_url'] ) ) {
			return $session_data['woopay_source_url'];
		}

		return '';
	}

	/**
	 * Get if the request comes from blocks checkout.
	 *
	 * @return boolean
	 */
	public function get_woopay_is_blocks() {
		$session_data = WC()->session->get( WooPay_Session::WOOPAY_SESSION_KEY );

		$woopay_is_blocks_post    = isset( $_POST['woopay_is_blocks'] ) && filter_var( wp_unslash( $_POST['woopay_is_blocks'] ), FILTER_VALIDATE_BOOLEAN ); // phpcs:ignore WordPress.Security.NonceVerification
		$woopay_is_blocks_session = isset( $session_data['woopay_is_blocks'] ) && filter_var( $session_data['woopay_is_blocks'], FILTER_VALIDATE_BOOLEAN );

		return $woopay_is_blocks_post || $woopay_is_blocks_session;
	}

	/**
	 * Get the user viewport.
	 *
	 * @return mixed|string
	 */
	public function get_woopay_viewport() {
		$session_data = WC()->session->get( WooPay_Session::WOOPAY_SESSION_KEY );

		if ( ! empty( $_POST['woopay_viewport'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return wc_clean( wp_unslash( $_POST['woopay_viewport'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( ! empty( $session_data['woopay_viewport'] ) ) {
			return $session_data['woopay_viewport'];
		}

		return '';
	}

	/**
	 * Returns true if guest checkout is enabled, false otherwise.
	 *
	 * @return bool  True if guest checkout is enabled, false otherwise.
	 */
	public function is_guest_checkout_enabled(): bool {
		return 'yes' === get_option( 'woocommerce_enable_guest_checkout', 'no' );
	}

	/**
	 * Builds the WooPay rest url for a given endpoint
	 *
	 * @param string $endpoint the end point.
	 * @return string the endpoint full url.
	 */
	public static function get_woopay_rest_url( $endpoint ) {
		return self::get_woopay_url() . '/wp-json/platform-checkout/v1/' . $endpoint;
	}

	/**
	 * Returns the WooPay url.
	 *
	 * @return string the WooPay url.
	 */
	public static function get_woopay_url() {
		return defined( 'PLATFORM_CHECKOUT_HOST' ) ? PLATFORM_CHECKOUT_HOST : self::DEFAULT_WOOPAY_URL;
	}

	/**
	 * Get the store blog token.
	 *
	 * @return mixed|string the store blog token.
	 */
	public static function get_store_blog_token() {
		if ( self::get_woopay_url() === self::DEFAULT_WOOPAY_URL ) {
			// Using WooPay production: Use the blog token secret from the store blog.
			return Jetpack_Options::get_option( 'blog_token' );
		}

		/**
		 * Filters whether to use the store's blog token secret when connecting to a custom WooPay URL.
		 *
		 * @since 8.0.0
		 *
		 * @param bool $use_blog_token Whether to use the store's blog token secret.
		 */
		$use_store_blog_token = apply_filters( 'wcpay_woopay_use_blog_token', false );

		if ( $use_store_blog_token ) {
			// Requested to use the blog token secret from the store blog.
			return Jetpack_Options::get_option( 'blog_token' );
		} elseif ( defined( 'DEV_BLOG_TOKEN_SECRET' ) ) {
			// Has a defined dev blog token secret: Use it.
			return DEV_BLOG_TOKEN_SECRET;
		} else {
			Logger::log( __( 'WooPay blog_token is currently misconfigured.', 'woocommerce-payments' ) );
			return '';
		}
	}

	/**
	 * Derives a key for one use of the blog token, so no two uses share a secret.
	 *
	 * The blog token authenticates the Jetpack connection, encrypts these payloads and
	 * signs them, so using it directly would leave every exchange sharing one key and
	 * relying on payload shape to tell them apart. Shape is not a boundary.
	 *
	 * HKDF makes the direction part of the key, so an envelope sealed for one is not a
	 * valid envelope for the other and cannot be made into one without the blog token.
	 *
	 * @param string $purpose Label naming what the derived key may be used for.
	 *
	 * @return string The derived key, or an empty string when the blog token is unavailable.
	 */
	public static function derive_key_for( string $purpose ): string {
		$store_blog_token = self::get_store_blog_token();

		if ( empty( $store_blog_token ) ) {
			return '';
		}

		return hash_hkdf( 'sha256', $store_blog_token, 32, $purpose );
	}

	/**
	 * Return an array with encrypted and signed data.
	 *
	 * @param array $data The data to be encrypted and signed.
	 * @return array The encrypted and signed data.
	 */
	public static function encrypt_and_sign_data( $data ) {
		$store_blog_token = self::derive_key_for( self::SESSION_KEY_PURPOSE );

		if ( empty( $store_blog_token ) ) {
			return [];
		}

		$message = wp_json_encode( $data );

		// Generate an initialization vector (IV) for encryption.
		$iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );

		// Encrypt the JSON session.
		$session_encrypted = openssl_encrypt( $message, 'aes-256-cbc', $store_blog_token, OPENSSL_RAW_DATA, $iv );

		// Fail closed. Without this, a failed encryption is signed and shipped like any
		// other payload: hash_hmac() casts false to '', so the receiver is handed an empty
		// session carrying a valid HMAC of the empty string, and reads it as authentic but
		// empty rather than as something that went wrong. Returning nothing instead matches
		// the empty-token case above, which callers already treat as "no session to send".
		if ( false === $session_encrypted ) {
			Logger::log( 'Failed to encrypt the WooPay session data.' );

			return [];
		}

		// Create an HMAC hash for data integrity.
		$hash = hash_hmac( 'sha256', $iv . $session_encrypted, $store_blog_token );

		$data = [
			'session' => $session_encrypted,
			'iv'      => $iv,
			'hash'    => $hash,
		];

		return [
			'blog_id' => Jetpack_Options::get_option( 'id' ),
			'data'    => array_map( 'base64_encode', $data ),
		];
	}

	/**
	 * Decode data WooPay sealed for this store and return it.
	 *
	 * Each caller states which keys it will open, so no envelope reaches a verifier that had
	 * no reason to expect it. This store seals nothing with the keys listed here, so an
	 * envelope it produced is not a valid envelope at any of them, whatever its array keys
	 * are named. That is the separation `derive_key_for()` exists to provide.
	 *
	 * The undifferentiated blog token is one of the things a caller may list, and exactly
	 * one does: the connect exchange, which WooPay still seals that way for every store. It
	 * is listed rather than defaulted to, so deleting it later is a matter of finding the
	 * call sites that name it.
	 *
	 * @param array $data              The session, iv, and hash data for the encryption.
	 * @param array $accepted_purposes HKDF labels this caller will open, tried in order. A
	 *                                 null entry accepts the undifferentiated blog token.
	 * @return mixed The decoded data.
	 */
	public static function decrypt_signed_data( $data, array $accepted_purposes ) {
		$store_blog_token = self::get_store_blog_token();

		if ( empty( $store_blog_token ) ) {
			return null;
		}

		// Decode the data.
		$decoded_data_request = array_map( 'base64_decode', $data );

		$signed_payload = $decoded_data_request['iv'] . $decoded_data_request['data'];
		$key            = null;

		// Verify the HMAC hash before decryption to ensure data integrity, and let whichever
		// key verified it be the one that decrypts — trying the other would fail anyway.
		$candidates = [];

		foreach ( $accepted_purposes as $accepted_purpose ) {
			$candidates[] = null === $accepted_purpose
				? $store_blog_token
				: self::derive_key_for( $accepted_purpose );
		}

		foreach ( $candidates as $candidate ) {
			if ( '' !== $candidate && hash_equals( hash_hmac( 'sha256', $signed_payload, $candidate ), $decoded_data_request['hash'] ) ) {
				$key = $candidate;
				break;
			}
		}

		// If neither matches, the message may have been tampered with.
		if ( null === $key ) {
			return null;
		}

		// Decipher the data using the verified key and the IV.
		$decrypted_data = openssl_decrypt( $decoded_data_request['data'], 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $decoded_data_request['iv'] );

		if ( false === $decrypted_data ) {
			return null;
		}

		$decrypted_data = json_decode( $decrypted_data, true );

		return $decrypted_data;
	}

	/**
	 * Get the persisted available countries.
	 *
	 * @return array
	 */
	private static function get_persisted_available_countries() {
		$available_countries = json_decode( get_option( self::AVAILABLE_COUNTRIES_OPTION_NAME, self::AVAILABLE_COUNTRIES_DEFAULT ), true );

		if ( ! is_array( $available_countries ) ) {
			return json_decode( self::AVAILABLE_COUNTRIES_DEFAULT );
		}

		return $available_countries;
	}
}
