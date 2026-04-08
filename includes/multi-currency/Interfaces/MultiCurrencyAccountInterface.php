<?php
/**
 * Interface MultiCurrencyAccountInterface
 *
 * @package WooCommerce\Payments\MultiCurrency\Interfaces
 */

namespace WCPay\MultiCurrency\Interfaces;

defined( 'ABSPATH' ) || exit;

interface MultiCurrencyAccountInterface {

	/**
	 * Checks if the account is connected to the payment provider.
	 *
	 * @param bool $on_error Value to return on server error, defaults to false.
	 *
	 * @return bool True if the account is connected, false otherwise, $on_error on error.
	 */
	public function is_provider_connected( bool $on_error = false ): bool;

	/**
	 * Checks if the account has been rejected, assumes the value of false on any account retrieval error.
	 * Returns false if the account is not connected.
	 *
	 * Note: We might want to use a more generic method to check if the account is in an enabled or
	 * disabled state for better compatibility between V1 and V2.
	 *
	 * @return bool True if the account is connected and rejected, false otherwise or on error.
	 */
	public function is_account_rejected(): bool;

	/**
	 * Gets and caches the data for the account connected to this site.
	 *
	 * @param bool $force_refresh Forces data to be fetched from the server, rather than using the cache.
	 *
	 * @return array|bool Account data or false if failed to retrieve account data.
	 */
	public function get_cached_account_data( bool $force_refresh = false );

	/**
	 * Gets the customer currencies supported for the account.
	 *
	 * @return array Currencies.
	 */
	public function get_account_customer_supported_currencies(): array;

	/**
	 * Get list of countries supported by the provider.
	 */
	public function get_supported_countries(): array;

	/**
	 * Get provider onboarding page url.
	 *
	 * @return string
	 */
	public function get_provider_onboarding_page_url(): string;
}
