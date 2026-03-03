<?php
/**
 * Card Payment Method Definition
 *
 * @package WCPay\PaymentMethods\Configs\Definitions
 */

namespace WCPay\PaymentMethods\Configs\Definitions;

use WCPay\PaymentMethods\Configs\Interfaces\PaymentMethodDefinitionInterface;
use WCPay\PaymentMethods\Configs\Constants\PaymentMethodCapability;
use WCPay\PaymentMethods\Configs\Utils\PaymentMethodUtils;
use WCPay\Constants\Country_Test_Cards;

/**
 * Class implementing the Card payment method definition.
 */
class CardDefinition implements PaymentMethodDefinitionInterface {

	/**
	 * Get the internal ID for the payment method
	 *
	 * @return string
	 */
	public static function get_id(): string {
		return 'card';
	}

	/**
	 * Get the keywords for the payment method. These are used by the duplicate detection service.
	 *
	 * @return string[]
	 */
	public static function get_keywords(): array {
		return [ 'card', 'credit card', 'debit card', 'cc' ];
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
		return __( 'Card', 'woocommerce-payments' );
	}

	/**
	 * Get a dynamic title based on charge details from Stripe.
	 *
	 * For cards, this returns a title like "Visa credit card" based on the
	 * card network and funding type from the Stripe charge details.
	 *
	 * @param string $account_country The merchant's account country.
	 * @param array  $payment_details The payment method details from the Stripe charge.
	 *
	 * @return string|null The dynamic title, or null to use the default get_title().
	 */
	public static function get_title_from_charge_details( string $account_country, array $payment_details ): ?string {
		// TODO: to be implemented when fully removing the `CC_Payment_Method` class.
		return null;
	}

	/**
	 * Get the title of the payment method for the settings page.
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 *
	 * @return string
	 */
	public static function get_settings_label( ?string $account_country = null ): string {
		return __( 'Credit / Debit Cards', 'woocommerce-payments' );
	}

	/**
	 * Get the customer-facing description of the payment method
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public static function get_description( ?string $account_country = null ): string {
		return __(
			'Let your customers pay with major credit and debit cards without leaving your store.',
			'woocommerce-payments'
		);
	}

	/**
	 * Get the list of supported currencies
	 * Empty array means all currencies are supported
	 *
	 * @return string[] Array of currency codes
	 */
	public static function get_supported_currencies(): array {
		return [];
	}

	/**
	 * Get the list of supported countries
	 * Empty array means all countries are supported
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string[] Array of country codes
	 */
	public static function get_supported_countries( ?string $account_country = null ): array {
		return [];
	}

	/**
	 * Get the payment method capabilities
	 *
	 * @return string[]
	 */
	public static function get_capabilities(): array {
		return [
			PaymentMethodCapability::REFUNDS,
			PaymentMethodCapability::MULTI_CURRENCY,
			PaymentMethodCapability::TOKENIZATION,
			PaymentMethodCapability::CAPTURE_LATER,
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
		return plugins_url( 'assets/images/payment-methods/generic-card.svg', WCPAY_PLUGIN_FILE );
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
		return plugins_url( 'assets/images/payment-methods/generic-card-black.svg', WCPAY_PLUGIN_FILE );
	}

	/**
	 * Get the testing instructions for the payment method
	 *
	 * @param string $account_country The merchant's account country.
	 * @return string HTML string containing testing instructions
	 */
	public static function get_testing_instructions( string $account_country ): string {
		$test_card_number = Country_Test_Cards::get_test_card_for_country( $account_country );

		return sprintf(
			// Translators: %s is a test card number.
			__( 'Use test card <number>%s</number> or refer to our <a>testing guide</a>.', 'woocommerce-payments' ),
			$test_card_number
		);
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
