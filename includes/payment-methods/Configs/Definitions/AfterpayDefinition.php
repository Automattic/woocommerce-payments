<?php
/**
 * Afterpay Payment Method Definition
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
 * Class implementing the Afterpay payment method definition.
 */
class AfterpayDefinition implements PaymentMethodDefinitionInterface {

	/**
	 * Get the internal ID for the payment method
	 *
	 * @return string
	 */
	public static function get_id(): string {
		return 'afterpay_clearpay';
	}

	/**
	 * Get the keywords for the payment method. These are used by the duplicate detection service.
	 *
	 * @return string[]
	 */
	public static function get_keywords(): array {
		return [ 'afterpay', 'clearpay' ];
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
		if ( Country_Code::UNITED_KINGDOM === $account_country ) {
			return __( 'Clearpay', 'woocommerce-payments' );
		}

		if ( Country_Code::UNITED_STATES === $account_country ) {
			return __( 'Cash App Afterpay', 'woocommerce-payments' );
		}

		return __( 'Afterpay', 'woocommerce-payments' );
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
		if ( Country_Code::UNITED_KINGDOM === $account_country ) {
			return __( 'Allow customers to pay over time with Clearpay.', 'woocommerce-payments' );
		}

		if ( Country_Code::UNITED_STATES === $account_country ) {
			return __( 'Allow customers to pay over time with Cash App Afterpay.', 'woocommerce-payments' );
		}

		return __( 'Allow customers to pay over time with Afterpay.', 'woocommerce-payments' );
	}

	/**
	 * Get the list of supported currencies
	 *
	 * @return string[] Array of currency codes
	 */
	public static function get_supported_currencies(): array {
		return [
			Currency_Code::UNITED_STATES_DOLLAR,
			Currency_Code::CANADIAN_DOLLAR,
			Currency_Code::AUSTRALIAN_DOLLAR,
			Currency_Code::NEW_ZEALAND_DOLLAR,
			Currency_Code::POUND_STERLING,
		];
	}

	/**
	 * Get the list of supported countries
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 *
	 * @return string[] Array of country codes
	 */
	public static function get_supported_countries( ?string $account_country = null ): array {
		// only domestic transactions are supported.
		$supported_countries = [
			Country_Code::UNITED_STATES,
			Country_Code::CANADA,
			Country_Code::AUSTRALIA,
			Country_Code::NEW_ZEALAND,
			Country_Code::UNITED_KINGDOM,
		];

		if ( null !== $account_country && in_array( strtoupper( $account_country ), $supported_countries, true ) ) {
			return [ strtoupper( $account_country ) ];
		}

		return $supported_countries;
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
		if ( Country_Code::UNITED_KINGDOM === $account_country ) {
			return plugins_url( 'assets/images/payment-methods/clearpay.svg', WCPAY_PLUGIN_FILE );
		}

		if ( Country_Code::UNITED_STATES === $account_country ) {
			return plugins_url( 'assets/images/payment-methods/afterpay-cashapp-logo.svg', WCPAY_PLUGIN_FILE );
		}

		return plugins_url( 'assets/images/payment-methods/afterpay-badge.svg', WCPAY_PLUGIN_FILE );
	}

	/**
	 * Get the URL for the payment method's dark mode icon
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 *
	 * @return string Returns regular icon URL if no dark mode icon exists
	 */
	public static function get_dark_icon_url( ?string $account_country = null ): string {
		if ( Country_Code::UNITED_KINGDOM === $account_country ) {
			return plugins_url( 'assets/images/payment-methods/clearpay.svg', WCPAY_PLUGIN_FILE );
		}

		if ( Country_Code::UNITED_STATES === $account_country ) {
			return plugins_url( 'assets/images/payment-methods/afterpay-cashapp-logo-dark.svg', WCPAY_PLUGIN_FILE );
		}

		return plugins_url( 'assets/images/payment-methods/afterpay-badge.svg', WCPAY_PLUGIN_FILE );
	}

	/**
	 * Get the URL for the payment method's settings icon
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 *
	 * @return string
	 */
	public static function get_settings_icon_url( ?string $account_country = null ): string {
		if ( Country_Code::UNITED_KINGDOM === $account_country ) {
			return plugins_url( 'assets/images/payment-methods/clearpay.svg', WCPAY_PLUGIN_FILE );
		}

		if ( Country_Code::UNITED_STATES === $account_country ) {
			return plugins_url( 'assets/images/payment-methods/afterpay-cashapp-badge.svg', WCPAY_PLUGIN_FILE );
		}

		return plugins_url( 'assets/images/payment-methods/afterpay-logo.svg', WCPAY_PLUGIN_FILE );
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
