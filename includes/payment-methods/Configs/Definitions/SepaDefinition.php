<?php
/**
 * SEPA Direct Debit Payment Method Definition
 *
 * @package WCPay\PaymentMethods\Configs\Definitions
 */

namespace WCPay\PaymentMethods\Configs\Definitions;

use WCPay\PaymentMethods\Configs\Interfaces\PaymentMethodDefinitionInterface;
use WCPay\PaymentMethods\Configs\Constants\PaymentMethodCapability;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;
use WCPay\PaymentMethods\Configs\Utils\PaymentMethodUtils;

/**
 * Class implementing the SEPA Direct Debit payment method definition.
 */
class SepaDefinition implements PaymentMethodDefinitionInterface {

	/**
	 * Get the internal ID for the payment method
	 *
	 * @return string
	 */
	public static function get_id(): string {
		return 'sepa_debit';
	}

	/**
	 * Get the keywords for the payment method. These are used by the duplicate detection service.
	 *
	 * @return string[]
	 */
	public static function get_keywords(): array {
		return [ 'sepa' ];
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
		return __( 'SEPA Direct Debit', 'woocommerce-payments' );
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
		return __(
			'Reach 500 million customers and over 20 million businesses across the European Union.',
			'woocommerce-payments'
		);
	}

	/**
	 * Get the list of supported currencies
	 *
	 * @return string[] Array of currency codes
	 */
	public static function get_supported_currencies(): array {
		return [ Currency_Code::EURO ];
	}

	/**
	 * Get the list of supported countries
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 *
	 * @return string[] Array of country codes
	 */
	public static function get_supported_countries( ?string $account_country = null ): array {
		// https://stripe.com/en-br/resources/more/sepa-country-list#list-of-sepa-countries.
		$eu_countries = [
			Country_Code::AUSTRIA,
			Country_Code::BELGIUM,
			Country_Code::BULGARIA,
			Country_Code::CROATIA,
			Country_Code::CYPRUS,
			Country_Code::CZECHIA,
			Country_Code::DENMARK,
			Country_Code::ESTONIA,
			Country_Code::FINLAND,
			Country_Code::FRANCE,
			Country_Code::GERMANY,
			Country_Code::GREECE,
			Country_Code::HUNGARY,
			Country_Code::IRELAND,
			Country_Code::ITALY,
			Country_Code::LATVIA,
			Country_Code::LITHUANIA,
			Country_Code::LUXEMBOURG,
			Country_Code::MALTA,
			Country_Code::NETHERLANDS,
			Country_Code::POLAND,
			Country_Code::PORTUGAL,
			Country_Code::ROMANIA,
			Country_Code::SLOVAKIA,
			Country_Code::SLOVENIA,
			Country_Code::SPAIN,
			Country_Code::SWEDEN,
		];

		$additional_sepa_countries = [
			Country_Code::SWITZERLAND,
			Country_Code::UNITED_KINGDOM,
			Country_Code::SAN_MARINO,
			Country_Code::VATICAN_CITY,
			Country_Code::ANDORRA,
			Country_Code::MONACO,
			Country_Code::LIECHTENSTEIN,
			Country_Code::NORWAY,
			Country_Code::ICELAND,
		];

		return array_merge( $eu_countries, $additional_sepa_countries );
	}

	/**
	 * Get the payment method capabilities
	 *
	 * @return string[]
	 */
	public static function get_capabilities(): array {
		return [
			PaymentMethodCapability::REFUNDS,
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
		return plugins_url( 'assets/images/payment-methods/sepa-debit.svg', WCPAY_PLUGIN_FILE );
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
		return self::get_icon_url( $account_country );
	}

	/**
	 * Get the testing instructions for the payment method
	 *
	 * @param string $account_country The merchant's account country.
	 * @return string HTML string containing testing instructions
	 */
	public static function get_testing_instructions( string $account_country ): string {
		return __( '<strong>Test mode:</strong> use the test account number <number>AT611904300234573201</number>. Other payment methods may redirect to a Stripe test page to authorize payment. More test card numbers are listed <a>here</a>.', 'woocommerce-payments' );
	}

	/**
	 * Get the currency limits for the payment method
	 *
	 * @return array<string,array<string,array{min:int,max:int}>>
	 */
	public static function get_limits_per_currency(): array {
		return [];
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
		return null;
	}
}
