<?php
/**
 * Payment Method Utilities
 *
 * @package WCPay\PaymentMethods\Configs\Utils
 */

namespace WCPay\PaymentMethods\Configs\Utils;

use WCPay\PaymentMethods\Configs\Interfaces\PaymentMethodDefinitionInterface;

/**
 * Utility class for payment method related functions.
 */
class PaymentMethodUtils {
	/**
	 * Get the Stripe payment method ID.
	 * By default, this appends '_payments' to the payment method ID.
	 *
	 * @param string $payment_method_id The payment method ID.
	 * @return string
	 */
	public static function get_stripe_id( string $payment_method_id ): string {
		return $payment_method_id . '_payments';
	}

	/**
	 * Whether a payment method is available for the given currency and country
	 *
	 * @param PaymentMethodDefinitionInterface $payment_method   The payment method to check.
	 * @param string                           $currency         The currency code to check.
	 * @param string                           $account_country  The merchant's account country.
	 * @return bool
	 */
	public static function is_available_for( PaymentMethodDefinitionInterface $payment_method, string $currency, string $account_country ): bool {
		// Check if currency is supported.
		$supported_currencies = $payment_method->get_supported_currencies();
		if ( ! empty( $supported_currencies ) && ! in_array( $currency, $supported_currencies, true ) ) {
			return false;
		}

		// Check if country is supported.
		$supported_countries = $payment_method->get_supported_countries();
		if ( ! empty( $supported_countries ) && ! in_array( $account_country, $supported_countries, true ) ) {
			return false;
		}

		return true;
	}
}
