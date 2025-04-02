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
 * Mock payment method definition for testing.
 */
class MockPaymentMethodDefinition implements PaymentMethodDefinitionInterface {
	public static function get_id(): string {
		return 'mock_method';
	}

	public static function get_keywords(): array {
		return [ 'mock_method' ];
	}

	public static function get_stripe_id(): string {
		return 'mock_method_payments';
	}

	public static function get_payment_method_class(): string {
		return 'MockPaymentMethod';
	}

	public static function get_title( ?string $account_country = null ): string {
		return 'Mock Method';
	}

	public static function get_settings_label( ?string $account_country = null ): string {
		return 'Mock Method';
	}

	public static function get_description( ?string $account_country = null ): string {
		return 'Mock payment method for testing';
	}

	public static function is_bnpl(): bool {
		return false;
	}

	public static function is_reusable(): bool {
		return true;
	}

	public static function accepts_only_domestic_payments(): bool {
		return false;
	}

	public static function allows_manual_capture(): bool {
		return true;
	}

	public static function get_supported_currencies(): array {
		return [ 'USD', 'CAD' ];
	}

	public static function get_supported_countries(): array {
		return [ 'US', 'CA' ];
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
			in_array( $account_country, self::get_supported_countries(), true );
	}

	public static function is_enabled_by_default(): bool {
		return true;
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
