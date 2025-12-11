<?php
/**
 * Klarna Payment Method Definition
 *
 * @package WCPay\PaymentMethods\Configs\Definitions
 */

namespace WCPay\PaymentMethods\Configs\Definitions;

use WC_Payments_Utils;
use WCPay\PaymentMethods\Configs\Interfaces\PaymentMethodDefinitionInterface;
use WCPay\PaymentMethods\Configs\Constants\PaymentMethodCapability;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;
use WCPay\PaymentMethods\Configs\Utils\PaymentMethodUtils;

/**
 * Class implementing the Klarna payment method definition.
 */
class KlarnaDefinition implements PaymentMethodDefinitionInterface {

	/**
	 * Get the internal ID for the payment method
	 *
	 * @return string
	 */
	public static function get_id(): string {
		return 'klarna';
	}

	/**
	 * Get the keywords for the payment method. These are used by the duplicate detection service.
	 *
	 * @return string[]
	 */
	public static function get_keywords(): array {
		return [ 'klarna' ];
	}

	/**
	 * Get the Stripe payment method ID
	 *
	 * @return string
	 */
	public static function get_stripe_id(): string {
		return PaymentMethodUtils::get_stripe_id( self::get_id() );
	}

	/**
	 * Get the customer-facing title of the payment method
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 *
	 * @return string
	 */
	public static function get_title( ?string $account_country = null ): string {
		return __( 'Klarna', 'woocommerce-payments' );
	}

	/**
	 * Get the title of the payment method for the settings page.
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 *
	 * @return string
	 */
	public static function get_settings_label( ?string $account_country = null ): string {
		return self::get_title( $account_country );
	}

	/**
	 * Get the customer-facing description of the payment method
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public static function get_description( ?string $account_country = null ): string {
		return __( 'Allow customers to pay over time or pay now with Klarna.', 'woocommerce-payments' );
	}

	/**
	 * Get the list of supported currencies
	 *
	 * @return string[] Array of currency codes
	 */
	public static function get_supported_currencies(): array {
		return [
			Currency_Code::UNITED_STATES_DOLLAR,
			Currency_Code::POUND_STERLING,
			Currency_Code::EURO,
			Currency_Code::DANISH_KRONE,
			Currency_Code::NORWEGIAN_KRONE,
			Currency_Code::SWEDISH_KRONA,
		];
	}

	/**
	 * Get the list of supported countries.
	 *
	 * Klarna has domestic transaction restrictions with special handling for EEA merchants:
	 * - Non-EEA merchants (e.g., US): Only domestic transactions allowed (single country).
	 * - EEA/UK/Switzerland merchants: Cross-border within EEA allowed, but only to countries
	 *   that share the store's domestic currency.
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 *                                     Required for domestic/EEA cross-border logic.
	 * @return string[] Array of country codes
	 */
	public static function get_supported_countries( ?string $account_country = null ): array {
		// All countries where Klarna is available.
		$all_supported_countries = [
			Country_Code::UNITED_STATES,
			Country_Code::UNITED_KINGDOM,
			Country_Code::AUSTRIA,
			Country_Code::GERMANY,
			Country_Code::NETHERLANDS,
			Country_Code::BELGIUM,
			Country_Code::SPAIN,
			Country_Code::ITALY,
			Country_Code::IRELAND,
			Country_Code::DENMARK,
			Country_Code::FINLAND,
			Country_Code::NORWAY,
			Country_Code::SWEDEN,
			Country_Code::FRANCE,
		];

		if ( null === $account_country ) {
			return $all_supported_countries;
		}

		$account_country = strtoupper( $account_country );

		// Countries in the EEA can transact across all other EEA countries.
		// This includes Switzerland and the UK who aren't strictly in the EU.
		$eea_extended_countries = array_merge(
			WC_Payments_Utils::get_european_economic_area_countries(),
			[ Country_Code::SWITZERLAND, Country_Code::UNITED_KINGDOM ]
		);

		// If the merchant is NOT in the EEA/UK/Switzerland, apply strict domestic restriction.
		if ( ! in_array( $account_country, $eea_extended_countries, true ) ) {
			// Non-EEA merchants can only accept domestic transactions.
			if ( in_array( $account_country, $all_supported_countries, true ) ) {
				return [ $account_country ];
			}
			return $all_supported_countries;
		}

		// Merchant is in EEA/UK/Switzerland - apply cross-border currency logic.
		$store_currency = strtoupper( \get_woocommerce_currency() );
		$limits         = self::get_limits_per_currency();

		// If the store currency is not supported by Klarna, return no countries.
		if ( ! isset( $limits[ $store_currency ] ) ) {
			return [ 'NONE_SUPPORTED' ];
		}

		// Only countries that support the store's currency are eligible.
		$countries_supporting_currency = array_keys( $limits[ $store_currency ] );

		return array_values( array_intersect( $eea_extended_countries, $countries_supporting_currency ) );
	}

	/**
	 * Get the payment method capabilities
	 *
	 * @return string[]
	 */
	public static function get_capabilities(): array {
		return [
			PaymentMethodCapability::BUY_NOW_PAY_LATER,
			PaymentMethodCapability::REFUNDS,
			PaymentMethodCapability::DOMESTIC_TRANSACTIONS_ONLY,
		];
	}

	/**
	 * Get the URL for the payment method's icon
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 *
	 * @return string
	 */
	public static function get_icon_url( ?string $account_country = null ): string {
		return plugins_url( 'assets/images/payment-methods/klarna-pill.svg', WCPAY_PLUGIN_FILE );
	}

	/**
	 * Get the URL for the payment method's dark mode icon
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 *
	 * @return string Returns regular icon URL if no dark mode icon exists
	 */
	public static function get_dark_icon_url( ?string $account_country = null ): string {
		return self::get_icon_url( $account_country );
	}

	/**
	 * Get the URL for the payment method's settings icon
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 *
	 * @return string
	 */
	public static function get_settings_icon_url( ?string $account_country = null ): string {
		return plugins_url( 'assets/images/payment-methods/klarna.svg', WCPAY_PLUGIN_FILE );
	}

	/**
	 * Get the testing instructions for the payment method
	 *
	 * @param string $account_country The merchant's account country.
	 * @return string HTML string containing testing instructions
	 */
	public static function get_testing_instructions( string $account_country ): string {
		return '';
	}

	/**
	 * Get the currency limits for the payment method
	 *
	 * @return array<string,array<string,array{min:int,max:int}>>
	 */
	public static function get_limits_per_currency(): array {
		return WC_Payments_Utils::get_bnpl_limits_per_currency( self::get_id() );
	}

	/**
	 * Whether this payment method is available for the given currency and country
	 *
	 * @param string $currency The currency code to check.
	 * @param string $account_country The merchant's account country.
	 *
	 * @return bool
	 */
	public static function is_available_for( string $currency, string $account_country ): bool {
		return PaymentMethodUtils::is_available_for( self::get_supported_currencies(), self::get_supported_countries( $account_country ), $currency, $account_country );
	}

	/**
	 * Get the minimum amount for this payment method for a given currency and country
	 *
	 * @param string $currency The currency code.
	 * @param string $country The country code.
	 *
	 * @return int|null The minimum amount or null if no minimum.
	 */
	public static function get_minimum_amount( string $currency, string $country ): ?int {
		$limits = self::get_limits_per_currency();

		if ( isset( $limits[ $currency ][ $country ]['min'] ) ) {
			return $limits[ $currency ][ $country ]['min'];
		}

		return null;
	}

	/**
	 * Get the maximum amount for this payment method for a given currency and country
	 *
	 * @param string $currency The currency code.
	 * @param string $country The country code.
	 *
	 * @return int|null The maximum amount or null if no maximum.
	 */
	public static function get_maximum_amount( string $currency, string $country ): ?int {
		$limits = self::get_limits_per_currency();

		if ( isset( $limits[ $currency ][ $country ]['max'] ) ) {
			return $limits[ $currency ][ $country ]['max'];
		}

		return null;
	}
}
