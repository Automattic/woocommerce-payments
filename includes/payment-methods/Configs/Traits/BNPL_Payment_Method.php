<?php
/**
 * BNPL Payment Method Trait
 *
 * @package WCPay\PaymentMethods\Configs\Traits
 */

namespace WCPay\PaymentMethods\Configs\Traits;

/**
 * Trait for Buy Now Pay Later payment methods.
 */
trait BNPL_Payment_Method {
	/**
	 * Get the validation rules for the BNPL payment method
	 *
	 * @return array<string,array<string,array{min:int,max:int}>> Array of currency limits indexed by currency code and country code.
	 */
	abstract public function get_limits_per_currency(): array;

	/**
	 * Whether the payment amount is within the allowed limits for the given currency and country
	 *
	 * @param int    $amount         The amount to check.
	 * @param string $currency       The currency code.
	 * @param string $country        The country code.
	 * @return bool
	 */
	public function is_amount_within_limits( int $amount, string $currency, string $country ): bool {
		$limits = $this->get_limits_per_currency();

		if ( ! isset( $limits[ $currency ][ $country ] ) ) {
			return false;
		}

		$currency_limits = $limits[ $currency ][ $country ];
		return $amount >= $currency_limits['min'] && $amount <= $currency_limits['max'];
	}

	/**
	 * Get minimum amount for a currency and country
	 *
	 * @param string $currency The currency code.
	 * @param string $country  The country code.
	 * @return int|null
	 */
	public function get_minimum_amount( string $currency, string $country ): ?int {
		return $this->get_limits_per_currency()[ $currency ][ $country ]['min'] ?? null;
	}

	/**
	 * Get maximum amount for a currency and country
	 *
	 * @param string $currency The currency code.
	 * @param string $country  The country code.
	 * @return int|null
	 */
	public function get_maximum_amount( string $currency, string $country ): ?int {
		return $this->get_limits_per_currency()[ $currency ][ $country ]['max'] ?? null;
	}
}
