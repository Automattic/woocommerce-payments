<?php
/**
 * Affirm Payment Method Definition
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
 * Class implementing the Affirm payment method definition.
 */
class AffirmDefinition implements PaymentMethodDefinitionInterface {

	/**
	 * Get the internal ID for the payment method
	 *
	 * @return string
	 */
	public static function get_id(): string {
		return 'affirm';
	}

	/**
	 * Get the keywords for the payment method
	 *
	 * @return string[]
	 */
	public static function get_keywords(): array {
		return [ 'affirm' ];
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
		return \WCPay\Payment_Methods\Affirm_Payment_Method::class;
	}

	/**
	 * Get the customer-facing title of the payment method
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public static function get_title( ?string $account_country = null ): string {
		return __( 'Affirm', 'woocommerce-payments' );
	}

	/**
	 * Get the customer-facing description of the payment method
	 *
	 * @return string
	 */
	public static function get_description(): string {
		return __( 'Allow customers to pay over time with Affirm.', 'woocommerce-payments' );
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
	public static function does_accept_only_domestic_payments(): bool {
		return PaymentMethodUtils::does_accept_only_domestic_payments( self::get_capabilities() );
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
			Country_Code::CANADA,
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
		return self::get_id() . '-logo';
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
			Currency_Code::CANADIAN_DOLLAR      => [
				Country_Code::CANADA => [
					// min C$50.00.
					'min' => 5000,
					// max C$30,000.00.
					'max' => 3000000,
				],
			],
			Currency_Code::UNITED_STATES_DOLLAR => [
				Country_Code::UNITED_STATES => [
					// min $50.00.
					'min' => 5000,
					// max $30,000.00.
					'max' => 3000000,
				],
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
	 * For Affirm, the currency must match the country (USD for US, CAD for Canada).
	 *
	 * @param string $currency        The currency code to check.
	 * @param string $account_country The merchant's account country.
	 * @return bool True if the payment method meets all additional availability constraints.
	 */
	private static function meets_availability_constraints( string $currency, string $account_country ): bool {
		return ( Currency_Code::UNITED_STATES_DOLLAR === $currency && Country_Code::UNITED_STATES === $account_country ) ||
				( Currency_Code::CANADIAN_DOLLAR === $currency && Country_Code::CANADA === $account_country );
	}

	/**
	 * Whether this payment method is enabled by default
	 *
	 * @return bool
	 */
	public static function is_enabled_by_default(): bool {
		return false;
	}

	/**
	 * Get minimum amount for a currency and country
	 *
	 * @param string $currency The currency code.
	 * @param string $country  The country code.
	 * @return int|null
	 */
	public static function get_minimum_amount( string $currency, string $country ): ?int {
		return self::get_limits_per_currency()[ $currency ][ $country ]['min'] ?? null;
	}

	/**
	 * Get maximum amount for a currency and country
	 *
	 * @param string $currency The currency code.
	 * @param string $country  The country code.
	 * @return int|null
	 */
	public static function get_maximum_amount( string $currency, string $country ): ?int {
		return self::get_limits_per_currency()[ $currency ][ $country ]['max'] ?? null;
	}
}
