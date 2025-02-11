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
interface PaymentMethodDefinitionInterface {
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
	 * Get the URL for the payment method's icon
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string
	 */
	public function get_icon_url( ?string $account_country = null ): string;

	/**
	 * Get the URL for the payment method's dark mode icon
	 *
	 * @param string|null $account_country Optional. The merchant's account country.
	 * @return string Returns regular icon URL if no dark mode icon exists
	 */
	public function get_dark_icon_url( ?string $account_country = null ): string;

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

	/**
	 * Get the currency limits for the payment method
	 *
	 * @return array<string,array<string,array{min:int,max:int}>>
	 */
	public function get_limits_per_currency(): array;

	/**
	 * Get minimum amount for a currency and country
	 *
	 * @param string $currency The currency code.
	 * @param string $country  The country code.
	 * @return int|null Returns null if no limit is set
	 */
	public function get_minimum_amount( string $currency, string $country ): ?int;

	/**
	 * Get maximum amount for a currency and country
	 *
	 * @param string $currency The currency code.
	 * @param string $country  The country code.
	 * @return int|null Returns null if no limit is set
	 */
	public function get_maximum_amount( string $currency, string $country ): ?int;
}
