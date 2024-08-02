<?php
/**
 * Interface MultiCurrencyApiClientInterface
 *
 * @package WooCommerce\Payments\MultiCurrency\Interfaces
 */

namespace WCPay\MultiCurrency\Interfaces;

defined( 'ABSPATH' ) || exit;

interface MultiCurrencyApiClientInterface {

	/**
	 * Whether the API client is connected to the server.
	 *
	 * @return bool
	 */
	public function is_server_connected(): bool;

	/**
	 * Get currency rates from the server.
	 *
	 * @param string $currency_from - The currency to convert from.
	 * @param ?array $currencies_to - An array of the currencies we want to convert into. If left empty, will get all supported currencies.
	 *
	 * @return array
	 */
	public function get_currency_rates( string $currency_from, $currencies_to = null ): array;

	/**
	 * Fetch a single intent with provided id.
	 * Note: This can be removed if OrderMetaHelper is moved out of multi-currency.
	 *
	 * @param string $intent_id intent id.
	 *
	 * @return object Payment Intent object.
	 */
	public function get_intent( string $intent_id );
}
