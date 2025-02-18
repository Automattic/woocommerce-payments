<?php
/**
 * Klarna Payment Method Definition
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
	 * Get the keywords for the payment method
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
	 * Get the payment method class name that implements this definition.
	 *
	 * @return class-string The payment method class name.
	 */
	public static function get_payment_method_class(): string {
		return \WCPay\Payment_Methods\Klarna_Payment_Method::class;
	}

	/**
	 * Get the customer-facing title of the payment method
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public static function get_title( ?string $account_country = null ): string {
		return __( 'Klarna', 'woocommerce-payments' );
	}

	/**
	 * Get the customer-facing description of the payment method
	 *
	 * @return string
	 */
	public static function get_description(): string {
		return __( 'Allow customers to pay over time or pay now with Klarna.', 'woocommerce-payments' );
	}

	/**
	 * Is the payment method a BNPL (Buy Now Pay Later) payment method?
	 *
	 * @return boolean
	 */
	public static function is_bnpl(): bool {
		return PaymentMethodUtils::is_bnpl( self::get_capabilities() );
	}

	/**
	 * Is the payment method a reusable payment method?
	 *
	 * @return boolean
	 */
	public static function is_reusable(): bool {
		return PaymentMethodUtils::is_reusable( self::get_capabilities() );
	}

	/**
	 * Does the payment method accept only domestic payments?
	 *
	 * @return boolean
	 */
	public static function accepts_only_domestic_payments(): bool {
		return PaymentMethodUtils::accepts_only_domestic_payments( self::get_capabilities() );
	}

	/**
	 * Does the payment method allow manual capture?
	 *
	 * @return boolean
	 */
	public static function allows_manual_capture(): bool {
		return PaymentMethodUtils::allows_manual_capture( self::get_capabilities() );
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
	 * Get the list of supported countries
	 *
	 * @return string[] Array of country codes
	 */
	public static function get_supported_countries(): array {
		return [
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
	}

	/**
	 * Get the payment method capabilities
	 *
	 * @return string[]
	 */
	public static function get_capabilities(): array {
		return [
			PaymentMethodCapability::REFUNDS,
			PaymentMethodCapability::BUY_NOW_PAY_LATER,
			PaymentMethodCapability::MULTI_CURRENCY,
			PaymentMethodCapability::DOMESTIC_TRANSACTIONS_ONLY,
		];
	}

	/**
	 * Get the base filename for the payment method's icons.
	 *
	 * @return string
	 */
	private static function get_icon_filename_base(): string {
		return self::get_id() . '-pill';
	}

	/**
	 * Get the URL for the payment method's icon
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public static function get_icon_url( ?string $account_country = null ): string {
		return plugin_dir_url( WCPAY_PLUGIN_FILE ) . 'assets/images/payment-methods/' . self::get_icon_filename_base() . '.svg';
	}

	/**
	 * Get the URL for the payment method's dark mode icon
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string Returns regular icon URL if no dark mode icon exists
	 */
	public static function get_dark_icon_url( ?string $account_country = null ): string {
		$dark_icon_path = dirname( WCPAY_PLUGIN_FILE ) . '/assets/images/payment-methods/' . self::get_icon_filename_base() . '-dark.svg';
		if ( file_exists( $dark_icon_path ) ) {
			return plugin_dir_url( WCPAY_PLUGIN_FILE ) . 'assets/images/payment-methods/' . self::get_icon_filename_base() . '-dark.svg';
		}
		return self::get_icon_url( $account_country );
	}

	/**
	 * Get the URL for the payment method's settings icon
	 *
	 * @return string
	 */
	public static function get_settings_icon_url(): string {
		return plugin_dir_url( WCPAY_PLUGIN_FILE ) . 'assets/images/payment-methods/klarna.svg';
	}

	/**
	 * Get the testing instructions for the payment method
	 *
	 * @return string HTML string containing testing instructions
	 */
	public static function get_testing_instructions(): string {
		return '';
	}

	/**
	 * Get the currency limits for the payment method
	 *
	 * @return array<string,array<string,array{min:int,max:int}>>
	 */
	public static function get_limits_per_currency(): array {
		return [
			Currency_Code::UNITED_STATES_DOLLAR => [
				Country_Code::UNITED_STATES => [
					'min' => 100,
					'max' => 1000000,
				], // Represents USD 1 - 10,000 USD.
			],
			Currency_Code::POUND_STERLING       => [
				Country_Code::UNITED_KINGDOM => [
					'min' => 100,
					'max' => 500000,
				], // Represents GBP 1 - 5,000 GBP.
			],
			Currency_Code::EURO                 => [
				Country_Code::AUSTRIA     => [
					'min' => 100,
					'max' => 1000000,
				], // Represents EUR 1 - 10,000 EUR.
				Country_Code::BELGIUM     => [
					'min' => 100,
					'max' => 1000000,
				], // Represents EUR 1 - 10,000 EUR.
				Country_Code::GERMANY     => [
					'min' => 100,
					'max' => 1000000,
				], // Represents EUR 1 - 10,000 EUR.
				Country_Code::NETHERLANDS => [
					'min' => 100,
					'max' => 500000,
				], // Represents EUR 1 - 5,000 EUR.
				Country_Code::FINLAND     => [
					'min' => 100,
					'max' => 1000000,
				], // Represents EUR 1 - 10,000 EUR.
				Country_Code::SPAIN       => [
					'min' => 100,
					'max' => 1000000,
				], // Represents EUR 1 - 10,000 EUR.
				Country_Code::IRELAND     => [
					'min' => 100,
					'max' => 400000,
				], // Represents EUR 1 - 4,000 EUR.
				Country_Code::ITALY       => [
					'min' => 100,
					'max' => 400000,
				], // Represents EUR 1 - 4,000 EUR.
				Country_Code::FRANCE      => [
					'min' => 100,
					'max' => 400000,
				], // Represents EUR 1 - 4,000 EUR.
			],
			Currency_Code::DANISH_KRONE         => [
				Country_Code::DENMARK => [
					'min' => 100,
					'max' => 10000000,
				], // Represents DKK 1 - 100,000 DKK.
			],
			Currency_Code::NORWEGIAN_KRONE      => [
				Country_Code::NORWAY => [
					'min' => 100,
					'max' => 10000000,
				], // Represents NOK 1 - 100,000 NOK.
			],
			Currency_Code::SWEDISH_KRONA        => [
				Country_Code::SWEDEN => [
					'min' => 100,
					'max' => 10000000,
				], // Represents SEK 1 - 100,000 SEK.
			],
		];
	}

	/**
	 * Whether this payment method is available for the given currency and country
	 *
	 * @param string $currency        The currency code to check.
	 * @param string $account_country The merchant's account country.
	 * @return bool
	 */
	public static function is_available_for( string $currency, string $account_country ): bool {
		if ( ! PaymentMethodUtils::is_available_for( self::get_supported_currencies(), self::get_supported_countries(), $currency, $account_country ) ) {
			return false;
		}

		return self::meets_availability_constraints( $currency, $account_country );
	}

	/**
	 * Check if the payment method meets additional availability constraints beyond currency and country support.
	 * For Klarna, we need to check if the currency matches the country's domestic currency.
	 *
	 * @param string $currency        The currency code to check.
	 * @param string $account_country The merchant's account country.
	 * @return bool True if the payment method meets all additional availability constraints.
	 */
	private static function meets_availability_constraints( string $currency, string $account_country ): bool {
		$limits = self::get_limits_per_currency()[ $currency ] ?? [];
		return isset( $limits[ $account_country ] );
	}

	/**
	 * Whether this payment method should be enabled by default
	 *
	 * @return bool
	 */
	public static function is_enabled_by_default(): bool {
		return true;
	}

	/**
	 * Get the minimum amount for this payment method for a given currency and country
	 *
	 * @param string $currency The currency code.
	 * @param string $country  The country code.
	 * @return int|null The minimum amount or null if no minimum.
	 */
	public static function get_minimum_amount( string $currency, string $country ): ?int {
		$limits = self::get_limits_per_currency()[ $currency ][ $country ] ?? null;
		return $limits ? $limits['min'] : null;
	}

	/**
	 * Get the maximum amount for this payment method for a given currency and country
	 *
	 * @param string $currency The currency code.
	 * @param string $country  The country code.
	 * @return int|null The maximum amount or null if no maximum.
	 */
	public static function get_maximum_amount( string $currency, string $country ): ?int {
		$limits = self::get_limits_per_currency()[ $currency ][ $country ] ?? null;
		return $limits ? $limits['max'] : null;
	}
}
