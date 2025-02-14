<?php
/**
 * Class Fraud_Prevention_Service
 *
 * @package WCPay\Fraud_Prevention
 */

namespace WCPay\Fraud_Prevention;

use WC_Payment_Gateway_WCPay;
use WC_Payments;

/**
 * Class Fraud_Prevention_Service
 */
class Fraud_Prevention_Service {

	const TOKEN_NAME = 'wcpay-fraud-prevention-token';

	/**
	 * Singleton instance.
	 *
	 * @var Fraud_Prevention_Service
	 */
	private static $instance;

	/**
	 * Session instance.
	 *
	 * @var \WC_Session
	 */
	private $session;

	/**
	 * Instance of WC_Payment_Gateway_WCPay.
	 *
	 * @var WC_Payment_Gateway_WCPay
	 */
	private $wcpay_gateway;

	/**
	 * Fraud_Prevention_Service constructor.
	 *
	 * @param \WC_Session              $session       Session instance.
	 * @param WC_Payment_Gateway_WCPay $wcpay_gateway Instance of WC_Payment_Gateway_WCPay.
	 */
	public function __construct( \WC_Session $session, WC_Payment_Gateway_WCPay $wcpay_gateway ) {
		$this->session       = $session;
		$this->wcpay_gateway = $wcpay_gateway;
	}

	/**
	 * Returns singleton instance.
	 *
	 * @param null $session Session instance.
	 * @param null $gateway WC_Payment_Gateway_WCPay instance.
	 * @return Fraud_Prevention_Service
	 */
	public static function get_instance( $session = null, $gateway = null ): self {
		if ( null === self::$instance ) {
			self::$instance = new self( $session ?? WC()->session, $gateway ?? WC_Payments::get_gateway() );
		}

		return self::$instance;
	}

	/**
	 * Appends the fraud prevention token to the JS context if the protection is enabled, and a session exists.
	 * This token will also be used by express checkouts.
	 *
	 * @return  void
	 */
	public static function maybe_append_fraud_prevention_token() {
		if ( wp_script_is( self::TOKEN_NAME, 'enqueued' ) ) {
			return;
		}

		// Check session first before trying to append the token.
		if ( ! WC()->session ) {
			return;
		}

		$instance = self::get_instance();

		// Don't add the token if the prevention is not enabled.
		if ( ! $instance->is_enabled() ) {
			return;
		}

		// Don't add the token if the user isn't on the cart, checkout, product or pay for order page.
		// Checking the product and cart page too because the user can pay quickly via the payment buttons on that page.
		if ( ! is_checkout() && ! has_block( 'woocommerce/checkout' ) && ! is_cart() && ! is_product() && ! $instance->is_pay_for_order_page() ) {
			return;
		}

		wp_register_script( self::TOKEN_NAME, '', [], time(), true );
		wp_enqueue_script( self::TOKEN_NAME );
		// Add the fraud prevention token to the checkout configuration.
		wp_add_inline_script(
			self::TOKEN_NAME,
			"window.wcpayFraudPreventionToken = '" . esc_js( $instance->get_token() ) . "';",
			'after'
		);
	}

	/**
	 * Checks if this is the Pay for Order page.
	 *
	 * @return bool
	 */
	public function is_pay_for_order_page() {
		return is_checkout() && isset( $_GET['pay_for_order'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Sets a instance to be used in request cycle.
	 * Introduced primarily for supporting unit tests.
	 *
	 * @param Fraud_Prevention_Service|null $instance Instance of self.
	 */
	public static function set_instance( ?self $instance = null ) {
		self::$instance = $instance;
	}

	/**
	 * Checks if fraud prevention feature is enabled for the account.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->wcpay_gateway->is_card_testing_protection_eligible();
	}

	/**
	 * Returns current valid token.
	 *
	 * For the first page load generates the token,
	 * for consecutive loads - takes from session.
	 *
	 * @return string|mixed
	 */
	public function get_token(): string {
		$fraud_prevention_token = $this->session->get( self::TOKEN_NAME );
		if ( ! $fraud_prevention_token ) {
			$fraud_prevention_token = $this->regenerate_token();
		}
		return $fraud_prevention_token;
	}

	/**
	 * Generates a new token, persists in session and returns for immediate use.
	 *
	 * @return string
	 */
	public function regenerate_token(): string {
		$token = wp_generate_password( 16, false );
		$this->session->set( self::TOKEN_NAME, $token );
		return $token;
	}

	/**
	 * Verifies the token against POST data.
	 *
	 * @param string|null $token Token sent in request.
	 * @return bool
	 */
	public function verify_token( ?string $token = null ): bool {
		$session_token = $this->session->get( self::TOKEN_NAME );

		// Check if the tokens are both strings.
		if ( ! is_string( $session_token ) || ! is_string( $token ) ) {
			return false;
		}
		// Compare the hashes to check request validity.
		return hash_equals( $session_token, $token );
	}
}
