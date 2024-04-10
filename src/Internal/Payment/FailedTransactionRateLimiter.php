<?php
/**
 * Class FailedTransactionRateLimiter
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WCPay\Internal\Proxy\LegacyProxy;
use WCPay\Internal\Service\SessionService;

/**
 * A wrapper class for keeping track of events in registries, and to trigger a rate limiter after a threshold.
 */
class FailedTransactionRateLimiter extends AbstractSessionRateLimiter {

	/**
	 * FailedTransactionRateLimiter constructor.
	 *
	 * @param SessionService $session_service SessionService instance.
	 * @param LegacyProxy    $legacy_proxy    LegacyProxy instance.
	 */
	public function __construct(
		SessionService $session_service,
		LegacyProxy $legacy_proxy
	) {
		parent::__construct(
			'wcpay_card_declined_registry',
			5,
			10 * MINUTE_IN_SECONDS,
			$session_service,
			$legacy_proxy
		);
	}

	/**
	 * Checks if the rate limiter should be bumped based on error code.
	 *
	 * @param string $error_code Error code.
	 *
	 * @return bool
	 */
	public function should_bump_rate_limiter( string $error_code ): bool {
		return in_array( $error_code, [ 'card_declined', 'incorrect_number', 'incorrect_cvc' ], true );
	}
}
