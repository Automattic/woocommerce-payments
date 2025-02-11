<?php
/**
 * Payment Method Definition Interface
 *
 * @package WCPay\PaymentMethods\Configs\Interfaces
 */

namespace WCPay\PaymentMethods\Configs\Interfaces;

/**
 * Interface for defining payment method configurations.
 * Provides a single source of truth for both backend and frontend payment method properties.
 */
interface PaymentMethodDefinition {
	/**
	 * Get the internal ID for the payment method (e.g. 'card', 'klarna')
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Get the Stripe payment method ID (e.g. 'card_payments', 'klarna_payments')
	 *
	 * @return string
	 */
	public function get_stripe_id(): string;

	/**
	 * Get the customer-facing title of the payment method
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public function get_title( ?string $account_country = null ): string;

	/**
	 * Get the customer-facing description of the payment method
	 *
	 * @return string
	 */
	public function get_description(): string;

	/**
	 * Get the list of supported currencies
	 * Empty array means all currencies are supported
	 *
	 * @return string[] Array of currency codes
	 */
	public function get_supported_currencies(): array;

	/**
	 * Get the list of supported countries
	 * Empty array means all countries are supported
	 *
	 * @return string[] Array of country codes
	 */
	public function get_supported_countries(): array;

	/**
	 * Get the payment method capabilities
	 * Examples: tokenization, refunds, capture_later
	 *
	 * @return string[]
	 */
	public function get_capabilities(): array;

	/**
	 * Get the payment method icons configuration
	 *
	 * @return array{
	 *     default: array{
	 *         path: string,
	 *         dark_path?: string
	 *     }
	 * }
	 */
	public function get_icons(): array;

	/**
	 * Get the testing instructions for the payment method
	 *
	 * @return string HTML string containing testing instructions
	 */
	public function get_testing_instructions(): string;

	/**
	 * Whether this payment method is available for the given currency and country
	 *
	 * @param string $currency        The currency code to check.
	 * @param string $account_country The merchant's account country.
	 * @return bool
	 */
	public function is_available_for( string $currency, string $account_country ): bool;

	/**
	 * Whether this payment method is enabled by default
	 *
	 * @return bool
	 */
	public function is_enabled_by_default(): bool;
}
