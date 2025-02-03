<?php
/**
 * Payment Method Definition Interface
 *
 * @package WCPay\PaymentMethods\Configs\Interfaces
 */

namespace WCPay\PaymentMethods\Configs\Interfaces;

/**
 * Interface for payment method definitions.
 * Provides a single source of truth for payment method properties.
 * Only includes getters - no calculations or complex logic.
 */
interface PaymentMethodDefinition {
	/**
	 * Get the payment method ID (e.g. 'affirm', 'card')
	 * This is used for internal identification and frontend usage.
	 *
	 * @return string
	 */
	public static function get_id(): string;

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
	 * @return string|null
	 */
	public function get_dark_icon_url( ?string $account_country = null ): ?string;

	/**
	 * Get the testing instructions for the payment method
	 *
	 * @return string HTML string containing testing instructions
	 */
	public function get_testing_instructions(): string;

	/**
	 * Get the currency limits for the payment method
	 * Returns raw limits without any calculations
	 *
	 * @return array<string,array<string,array{min:int,max:int}>>
	 */
	public function get_currency_limits(): array;

	/**
	 * Whether this payment method is enabled by default
	 *
	 * @return bool
	 */
	public function is_enabled_by_default(): bool;

	/**
	 * Get the mapping of currencies to their domestic countries.
	 * Only relevant for payment methods with DOMESTIC_TRANSACTIONS_ONLY capability.
	 * Returns empty array if all currency/country combinations are allowed.
	 *
	 * @return array<string,string> Map of currency codes to their domestic country codes
	 */
	public function get_domestic_currency_mapping(): array;
}
