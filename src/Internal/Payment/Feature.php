<?php
/**
 * Class Feature
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Payment;

use WC_Payments_Account;
use WCPay\Internal\Payment\Factor;

/**
 * Until the new payment process is fully developed, and the legacy
 * process is gone, the new process is managed as a feature.
 *
 * This class will be removed once the new process is the default
 * option for all payments.
 */
class Feature {
	/**
	 * Remote account.
	 *
	 * @var WC_Payments_Account
	 */
	protected $account;

	/**
	 * Class constructor, receiving dependencies.
	 *
	 * @param WC_Payments_Account $account The currently active account.
	 */
	public function __construct( WC_Payments_Account $account ) {
		$this->account = $account;
	}

	/**
	 * Checks whether a given payment should use the new payment process.
	 *
	 * @param array $factors Factors, describing the type and conditions of the payment.
	 * @return bool
	 */
	public function should_use_new_payment_process( array $factors ): bool {
		$allowed_factors = $this->account->get_new_payment_process_enabled_factors();

		// This would make sure that the payment process is a factor as well.
		$factors[ Factor::NEW_PAYMENT_PROCESS ] = true;

		foreach ( $factors as $key => $enabled ) {
			// If a factor is not present, there is no need to check for it.
			if ( ! $enabled ) {
				continue;
			}

			// The factor should exist, and be allowed.
			if ( ! isset( $allowed_factors[ $key ] ) || ! $allowed_factors[ $key ] ) {
				return false;
			}
		}

		return true;
	}
}
