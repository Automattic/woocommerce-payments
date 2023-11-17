<?php
/**
 * Class FraudPreventionService
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Payments_Account;

/**
 * Class FraudPreventionService
 */
class FraudPreventionService {

	const TOKEN_NAME = 'wcpay-fraud-prevention-token';

	/**
	 * SessionService instance.
	 *
	 * @var SessionService
	 */
	private $session_service;

	/**
	 * Instance of WC_Payments_Account.
	 *
	 * @var WC_Payments_Account
	 */
	private $account_service;

	/**
	 * Constructor.
	 *
	 * @param SessionService      $session_service SessionService instance.
	 * @param WC_Payments_Account $account_service Legacy WC_Payments_Account instance.
	 */
	public function __construct( SessionService $session_service, WC_Payments_Account $account_service ) {
		$this->session_service = $session_service;
		$this->account_service = $account_service;
	}

	/**
	 * Checks if fraud prevention feature is enabled for the account.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->account_service->is_card_testing_protection_eligible();
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
		return $this->session_service->get( self::TOKEN_NAME ) ?? $this->regenerate_token();
	}

	/**
	 * Generates a new token, persists in session and returns for immediate use.
	 *
	 * @return string
	 */
	public function regenerate_token(): string {
		$token = wp_generate_password( 16, false );
		$this->session_service->set( self::TOKEN_NAME, $token );
		return $token;
	}

	/**
	 * Verifies the token against POST data.
	 *
	 * @param string|null $token Token sent in request.
	 * @return bool
	 */
	public function verify_token( ?string $token = null ): bool {
		if ( ! $this->is_enabled() ) {
			return true;
		}

		$session_token = $this->session_service->get( self::TOKEN_NAME );

		// Check if the tokens are both strings.
		if ( ! is_string( $session_token ) || ! is_string( $token ) ) {
			return false;
		}
		// Compare the hashes to check request validity.
		return hash_equals( $session_token, $token );
	}
}
