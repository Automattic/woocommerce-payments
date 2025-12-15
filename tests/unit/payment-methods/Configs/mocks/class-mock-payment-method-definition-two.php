<?php
/**
 * Mock payment method definitions for testing.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace WCPay\Tests\PaymentMethods\Configs;

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

	public static function get_payment_method_class(): string {
		return 'SecondMockPaymentMethod';
	}

	public static function get_title( ?string $account_country = null ): string {
		return 'Second Mock Method';
	}

	public static function get_settings_label( ?string $account_country = null ): string {
		return 'Second Mock Method';
	}

	public static function get_description( ?string $account_country = null ): string {
		return 'Second mock payment method for testing';
	}

	public static function get_supported_countries( ?string $account_country = null ): array {
		return [ 'US' ];
	}

	public static function get_supported_currencies(): array {
		return [ 'USD', 'EUR' ];
	}

	public static function get_capabilities(): array {
		return [];
	}

	public static function get_icon_url( ?string $account_country = null ): string {
		return 'https://example.com/icon.png';
	}

	public static function get_dark_icon_url( ?string $account_country = null ): string {
		return 'https://example.com/dark-icon.png';
	}

	public static function get_settings_icon_url( ?string $account_country = null ): string {
		return 'https://example.com/settings-icon.png';
	}

	public static function get_testing_instructions( string $account_country ): string {
		return 'Test instructions';
	}

	public static function is_available_for( string $currency, string $account_country ): bool {
		return in_array( $currency, self::get_supported_currencies(), true ) &&
			in_array( $account_country, self::get_supported_countries( $account_country ), true );
	}

	public static function get_limits_per_currency(): array {
		return [];
	}

	public static function get_minimum_amount( string $currency, string $country ): ?int {
		return null;
	}

	public static function get_maximum_amount( string $currency, string $country ): ?int {
		return null;
	}
}
