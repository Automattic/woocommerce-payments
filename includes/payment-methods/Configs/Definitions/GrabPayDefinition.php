<?php
/**
 * GrabPay Payment Method Definition
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
 * Class implementing the GrabPay payment method definition.
 */
class GrabPayDefinition implements PaymentMethodDefinitionInterface {

	/**
	 * Get the internal ID for the payment method
	 *
	 * @return string
	 */
	public static function get_id(): string {
		return 'grabpay';
	}

	/**
	 * Get the keywords for the payment method. These are used by the duplicate detection service.
	 *
	 * @return string[]
	 */
	public static function get_keywords(): array {
		return [ 'grabpay', 'grab_pay', 'grab' ];
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
		return __( 'GrabPay', 'woocommerce-payments' );
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
		return __( 'A popular digital wallet for cashless payments in Singapore.', 'woocommerce-payments' );
	}

	/**
	 * Get the list of supported currencies
	 *
	 * Note: If WooPayments becomes available to merchants from Malaysia in the future, we'll need to not only add MYR here,
	 * but also implement logic to limit the currency based on the Stripe account country,
	 * so SG accounts only accept SGD, and MY accounts only accept MYR.
	 *
	 * @return string[] Array of currency codes
	 */
	public static function get_supported_currencies(): array {
		return [ Currency_Code::SINGAPORE_DOLLAR ];
	}

	/**
	 * Get the list of supported countries
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string[] Array of country codes
	 */
	public static function get_supported_countries( ?string $account_country = null ): array {
		return [ Country_Code::SINGAPORE ];
	}

	/**
	 * Get the payment method capabilities
	 *
	 * @return string[]
	 */
	public static function get_capabilities(): array {
		return [
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
		return plugins_url( 'assets/images/payment-methods/grabpay.svg', WCPAY_PLUGIN_FILE );
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
		return '';
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
