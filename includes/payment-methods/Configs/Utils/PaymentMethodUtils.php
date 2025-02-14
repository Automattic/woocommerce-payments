<?php
/**
 * Payment Method Utilities
 *
 * @package WCPay\PaymentMethods\Configs\Utils
 */

namespace WCPay\PaymentMethods\Configs\Utils;

use WCPay\PaymentMethods\Configs\Constants\PaymentMethodCapability;
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
	 * @param array<string> $supported_currencies The list of supported currencies.
	 * @param array<string> $supported_countries  The list of supported countries.
	 * @param string        $currency             The currency code to check.
	 * @param string        $account_country      The merchant's account country.
	 * @return bool
	 */
	public static function is_available_for( array $supported_currencies, array $supported_countries, string $currency, string $account_country ): bool {
		// Check if currency is supported.
		if ( ! empty( $supported_currencies ) && ! in_array( $currency, $supported_currencies, true ) ) {
			return false;
		}

		// Check if country is supported.
		if ( ! empty( $supported_countries ) && ! in_array( $account_country, $supported_countries, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Is the payment method a BNPL (Buy Now Pay Later) payment method?
	 *
	 * @param array<string> $capabilities The payment method capabilities.
	 * @return boolean
	 */
	public static function is_bnpl( array $capabilities ): bool {
		return in_array( PaymentMethodCapability::BUY_NOW_PAY_LATER, $capabilities, true );
	}

	/**
	 * Is the payment method a reusable payment method?
	 *
	 * @param array<string> $capabilities The payment method capabilities.
	 * @return boolean
	 */
	public static function is_reusable( array $capabilities ): bool {
		return in_array( PaymentMethodCapability::TOKENIZATION, $capabilities, true );
	}

	/**
	 * Does the payment method accept only domestic payments?
	 *
	 * @param array<string> $capabilities The payment method capabilities.
	 * @return boolean
	 */
	public static function accepts_only_domestic_payments( array $capabilities ): bool {
		return in_array( PaymentMethodCapability::DOMESTIC_TRANSACTIONS_ONLY, $capabilities, true );
	}
}
