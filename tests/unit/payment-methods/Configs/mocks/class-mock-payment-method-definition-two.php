<?php
/**
 * Mock payment method definitions for testing.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\PaymentMethods\Configs;

use WCPay\Constants\Currency_Code;
use WCPay\PaymentMethods\Configs\Interfaces\PaymentMethodDefinitionInterface;
use WCPay\PaymentMethods\Configs\Utils\PaymentMethodUtils;

/**
 * Second mock payment method definition for testing multiple registrations.
 */
class SecondMockPaymentMethodDefinition implements PaymentMethodDefinitionInterface {
	public static function get_id(): string {
		return 'second_mock_method';
	}

	public static function get_keywords(): array {
		return [ 'second_mock_method' ];
	}

	public static function get_stripe_id(): string {
		return 'second_mock_method_payments';
	}

	public static function get_stripe_payment_method_type(): string {
		return self::get_id();
	}

	public static function get_payment_method_class(): string {
		return 'SecondMockPaymentMethod';
	}

	public static function get_title( ?string $_unused_account_country = null ): string {
		return 'Second Mock Method';
	}

	public static function get_title_from_charge_details( string $_unused_account_country, array $_unused_payment_details ): ?string {
		return null;
	}

	public static function get_settings_label( ?string $_unused_account_country = null ): string {
		return 'Second Mock Method';
	}

	public static function get_description( ?string $_unused_account_country = null ): string {
		return 'Second mock payment method for testing';
	}

	public static function get_supported_countries( ?string $_unused_account_country = null ): array {
		return [ 'US' ];
	}

	public static function get_supported_currencies(): array {
		return [ Currency_Code::UNITED_STATES_DOLLAR, Currency_Code::EURO ];
	}

	public static function get_capabilities(): array {
		return [];
	}

	public static function get_icon_url( ?string $_unused_account_country = null ): string {
		return 'https://example.com/icon.png';
	}

	public static function get_dark_icon_url( ?string $_unused_account_country = null ): string {
		return 'https://example.com/dark-icon.png';
	}

	public static function get_settings_icon_url( ?string $_unused_account_country = null ): string {
		return 'https://example.com/settings-icon.png';
	}

	public static function get_testing_instructions( string $_unused_account_country ): string {
		return 'Test instructions';
	}

	public static function is_available_for( string $currency, string $account_country ): bool {
		return in_array( $currency, self::get_supported_currencies(), true ) &&
			in_array( $account_country, self::get_supported_countries( $account_country ), true );
	}

	public static function get_limits_per_currency(): array {
		return [];
	}

	public static function get_minimum_amount( string $_unused_currency, string $_unused_country ): ?int {
		return null;
	}

	public static function get_maximum_amount( string $_unused_currency, string $_unused_country ): ?int {
		return null;
	}
}
