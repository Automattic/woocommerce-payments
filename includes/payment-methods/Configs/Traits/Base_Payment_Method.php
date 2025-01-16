<?php
/**
 * Base Payment Method Trait
 *
 * @package WCPay\PaymentMethods\Configs\Traits
 */

namespace WCPay\PaymentMethods\Configs\Traits;

/**
 * Trait for common payment method functionality.
 */
trait Base_Payment_Method {
	/**
	 * Get the Stripe payment method ID.
	 * By default, this appends '_payments' to the payment method ID. If Stripe changes their
	 * capability key, we'll need to update this. Individual payment method definitions can
	 * override this if its naming convention is different.
	 *
	 * @return string
	 */
	public function get_stripe_id(): string {
		return $this->get_id() . '_payments';
	}

	/**
	 * Whether this payment method is available for the given currency and country
	 *
	 * @param string $currency        The currency code to check.
	 * @param string $account_country The merchant's account country.
	 * @return bool
	 */
	public function is_available_for( string $currency, string $account_country ): bool {
		// Check if currency is supported.
		$supported_currencies = $this->get_supported_currencies();
		if ( ! empty( $supported_currencies ) && ! in_array( $currency, $supported_currencies, true ) ) {
			return false;
		}

		// Check if country is supported.
		$supported_countries = $this->get_supported_countries();
		if ( ! empty( $supported_countries ) && ! in_array( $account_country, $supported_countries, true ) ) {
			return false;
		}

		return $this->meets_availability_constraints( $currency, $account_country );
	}

	/**
	 * Check if the payment method meets additional availability constraints beyond currency and country support.
	 * Payment methods should override this method to add their specific constraints.
	 *
	 * @param string $currency        The currency code to check.
	 * @param string $account_country The merchant's account country.
	 * @return bool True if the payment method meets all additional availability constraints.
	 */
	protected function meets_availability_constraints( string $currency, string $account_country ): bool {
		return true;
	}
}
