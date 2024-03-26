<?php
/**
 * WooPay
 *
 * @package WCPay\WooPay
 */

namespace WCPay\WooPay;

use WC_Payments_Features;
use WC_Payments_Subscriptions_Utilities;
use WooPay_Extension;
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
	 * Checks various conditions to determine if WooPay should be enabled on the checkout page.
	 *
	 * This function should only be called when evaluating something for the checkout or cart page. The
	 * function will return false if you're on any other page.
	 *
	 * @return bool  True if WooPay should be enabled, false otherwise.
	 */
	public function should_enable_woopay_on_cart_or_checkout(): bool {
		if ( ! is_checkout() && ! has_block( 'woocommerce/checkout' ) && ! is_cart() && ! has_block( 'woocommerce/cart' ) ) {
			// Wrong usage, this should only be called for the checkout or cart page.
			return false;
		}

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
		return WC_Payments_Features::is_woopay_first_party_auth_enabled() && $this->is_country_available( WC_Payments::get_gateway() ); // Feature flag.
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
		$session_data = WC()->session->get( WooPay_Extension::WOOPAY_SESSION_KEY );

		return ( isset( $_POST['save_user_in_woopay'] ) && filter_var( wp_unslash( $_POST['save_user_in_woopay'] ), FILTER_VALIDATE_BOOLEAN ) ) || ( isset( $session_data['save_user_in_woopay'] ) && filter_var( $session_data['save_user_in_woopay'], FILTER_VALIDATE_BOOLEAN ) ); // phpcs:ignore WordPress.Security.NonceVerification
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
		$session_data = WC()->session->get( WooPay_Extension::WOOPAY_SESSION_KEY );

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
		$session_data = WC()->session->get( WooPay_Extension::WOOPAY_SESSION_KEY );

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
		$session_data = WC()->session->get( WooPay_Extension::WOOPAY_SESSION_KEY );

		return ( isset( $_POST['woopay_is_blocks'] ) && filter_var( wp_unslash( $_POST['woopay_is_blocks'] ), FILTER_VALIDATE_BOOLEAN ) ) || ( isset( $session_data['woopay_is_blocks'] ) && filter_var( $session_data['woopay_is_blocks'], FILTER_VALIDATE_BOOLEAN ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Get the user viewport.
	 *
	 * @return mixed|string
	 */
	public function get_woopay_viewport() {
		$session_data = WC()->session->get( WooPay_Extension::WOOPAY_SESSION_KEY );

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
	 * Returns true if an extension WooPay supports is installed .
	 *
	 * @return bool
	 */
	public function has_adapted_extension_installed() {
		foreach ( self::ADAPTED_EXTENSIONS as $supported_extension ) {
			if ( in_array( $supported_extension, apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return an array with encrypted and signed data.
	 * 
	 * @param array $data The data to be encrypted and signed.
	 * @return array The encrypted and signed data.
	 */
	public static function encrypt_and_sign_data( $data ) {
		$store_blog_token = ( self::get_woopay_url() === self::DEFAULT_WOOPAY_URL ) ? Jetpack_Options::get_option( 'blog_token' ) : 'dev_mode';

		$message = wp_json_encode( $data );

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

		return [
			'blog_id' => Jetpack_Options::get_option( 'id' ),
			'data'    => array_map( 'base64_encode', $data ),
		];
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
