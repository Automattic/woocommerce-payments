<?php
/**
 * Afterpay Payment Method Definition
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
	 * Get the keywords for the payment method
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
	 * @return string
	 */
	public static function get_title( ?string $account_country = null ): string {
		if ( 'GB' === $account_country ) {
			return __( 'Clearpay', 'woocommerce-payments' );
		}

		return __( 'Afterpay', 'woocommerce-payments' );
	}

	/**
	 * Get the customer-facing description of the payment method
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public static function get_description( ?string $account_country = null ): string {
		// translators: %s is the payment method title.
		return sprintf( __( 'Allow customers to pay over time with %s.', 'woocommerce-payments' ), self::get_title( $account_country ) );
	}

	/**
	 * Get the base filename for the payment method's icons.
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	private static function get_icon_filename_base( ?string $account_country = null ): string {
		if ( 'GB' === $account_country ) {
			return 'clearpay';
		}

		return 'afterpay-badge';
	}

	/**
	 * Get the URL for the payment method's icon
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public static function get_icon_url( ?string $account_country = null ): string {
		return plugin_dir_url( WCPAY_PLUGIN_FILE ) . 'assets/images/payment-methods/' . self::get_icon_filename_base( $account_country ) . '.svg';
	}

	/**
	 * Get the URL for the payment method's dark mode icon
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string Returns regular icon URL if no dark mode icon exists
	 */
	public static function get_dark_icon_url( ?string $account_country = null ): string {
		$dark_icon_path = dirname( WCPAY_PLUGIN_FILE ) . '/assets/images/payment-methods/' . self::get_icon_filename_base( $account_country ) . '-dark.svg';
		if ( file_exists( $dark_icon_path ) ) {
			return plugin_dir_url( WCPAY_PLUGIN_FILE ) . 'assets/images/payment-methods/' . self::get_icon_filename_base( $account_country ) . '-dark.svg';
		}
		return self::get_icon_url( $account_country );
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
	 * @return string[] Array of country codes
	 */
	public static function get_supported_countries(): array {
		return [
			Country_Code::UNITED_STATES,
			Country_Code::CANADA,
			Country_Code::AUSTRALIA,
			Country_Code::NEW_ZEALAND,
			Country_Code::UNITED_KINGDOM,
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
			Currency_Code::AUSTRALIAN_DOLLAR    => [
				Country_Code::AUSTRALIA => [
					'min' => 100,
					'max' => 200000,
				], // Represents AUD 1 - 2,000 AUD.
			],
			Currency_Code::CANADIAN_DOLLAR      => [
				Country_Code::CANADA => [
					'min' => 100,
					'max' => 200000,
				], // Represents CAD 1 - 2,000 CAD.
			],
			Currency_Code::NEW_ZEALAND_DOLLAR   => [
				Country_Code::NEW_ZEALAND => [
					'min' => 100,
					'max' => 200000,
				], // Represents NZD 1 - 2,000 NZD.
			],
			Currency_Code::POUND_STERLING       => [
				Country_Code::UNITED_KINGDOM => [
					'min' => 100,
					'max' => 120000,
				], // Represents GBP 1 - 1,200 GBP.
			],
			Currency_Code::UNITED_STATES_DOLLAR => [
				Country_Code::UNITED_STATES => [
					'min' => 100,
					'max' => 400000,
				], // Represents USD 1 - 4,000 USD.
			],
		];
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

	/**
	 * Whether this payment method is available for the given currency and country
	 *
	 * @param string $currency        The currency code to check.
	 * @param string $account_country The merchant's account country.
	 * @return bool
	 */
	public static function is_available_for( string $currency, string $account_country ): bool {
		return PaymentMethodUtils::is_available_for( self::get_supported_currencies(), self::get_supported_countries(), $currency, $account_country );
	}


	/**
	 * Get the payment method class name that implements this definition.
	 *
	 * @return class-string The payment method class name.
	 */
	public static function get_payment_method_class(): string {
		return \WCPay\Payment_Methods\Afterpay_Payment_Method::class;
	}
}
