<?php
/**
 * Class Fraud_Prevention_Service
 *
 * @package WCPay\Fraud_Prevention
 */

namespace WCPay\Fraud_Prevention;

use WC_Payments_Token_Service;

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
	 * Fraud_Prevention_Service constructor.
	 *
	 * @param \WC_Session $session Session instance.
	 */
	public function __construct( \WC_Session $session ) {
		$this->session = $session;
	}

	/**
	 * Returns singleton instance.
	 *
	 * @param null $session Session instance.
	 * @return Fraud_Prevention_Service
	 */
	public static function instance( $session = null ) {
		if ( null === self::$instance ) {
			if ( ! $session ) {
				$session = WC()->session;
			}

			self::$instance = new self( $session );
		}

		return self::$instance;
	}

	/**
	 * Checks if fraud prevention feature is enabled for the account.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		// TODO: depends on #3994.
		return true;
	}

	/**
	 * Returns current valid token.
	 *
	 * For the first page load generates the token,
	 * for consecutive loads - takes from session.
	 *
	 * @return string
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
		$token = wp_generate_password();
		WC()->session->set( self::TOKEN_NAME, $token );
		return $token;
	}

	/**
	 * Verifies the token against POST data.
	 *
	 * @param array $post_data Global $_POST array.
	 * @return bool
	 */
	public function verify_token( array $post_data ): bool {
		return isset( $post_data['wcpay-fraud-prevention-token'] ) && $this->session->get( self::TOKEN_NAME ) === $post_data['wcpay-fraud-prevention-token'];
	}
}
