<?php
/**
 * Payment Method Utilities
 *
 * @package WCPay\PaymentMethods\Configs\Utils
 */

namespace WCPay\PaymentMethods\Configs\Utils;

use WCPay\PaymentMethods\Configs\Constants\PaymentMethodCapability;
use WCPay\PaymentMethods\Configs\Registry\PaymentMethodDefinitionRegistry;

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

	/**
	 * Does the payment method allow manual capture?
	 *
	 * @param array<string> $capabilities The payment method capabilities.
	 * @return boolean
	 */
	public static function allows_manual_capture( array $capabilities ): bool {
		return in_array( PaymentMethodCapability::CAPTURE_LATER, $capabilities, true );
	}

	/**
	 * Checks if a currency is domestic for a given country.
	 *
	 * @param string $currency The currency code to check.
	 * @param string $country The country code to check against.
	 * @return bool True if the currency is domestic for the country
	 */
	public static function is_domestic_currency_for_country( string $currency, string $country ): bool {
		// Get the locale info which contains country->currency mapping.
		$locale_info = include WC()->plugin_path() . '/i18n/locale-info.php';

		// If country doesn't exist in our locale info, we can't validate.
		if ( ! isset( $locale_info[ $country ] ) ) {
			return false;
		}

		return $locale_info[ $country ]['currency_code'] === $currency;
	}

	/**
	 * Get the payment method definitions as a JSON string.
	 *
	 * @return string
	 */
	public static function get_payment_method_definitions_json() {
		$registry                   = PaymentMethodDefinitionRegistry::instance();
		$payment_method_definitions = [];

		foreach ( $registry->get_available_definitions() as $payment_method_definition ) {
			$payment_method_definitions[ $payment_method_definition::get_id() ] = [
				'id'                            => $payment_method_definition::get_id(),
				'stripe_key'                    => $payment_method_definition::get_stripe_id(),
				'title'                         => $payment_method_definition::get_title(),
				'description'                   => $payment_method_definition::get_description(),
				'settings_icon_url'             => $payment_method_definition::get_settings_icon_url(),
				'currencies'                    => $payment_method_definition::get_supported_currencies(),
				'allows_manual_capture'         => $payment_method_definition::allows_manual_capture(),
				'allows_pay_later'              => $payment_method_definition::is_bnpl(),
				'accepts_only_domestic_payment' => $payment_method_definition::accepts_only_domestic_payments(),
			];
		}

		$encoded_response = wp_json_encode( $payment_method_definitions );
		return false === $encoded_response ? '' : $encoded_response;
	}
}
