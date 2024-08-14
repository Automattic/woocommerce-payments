<?php
/**
 * Interface MultiCurrencyLocalizationInterface
 *
 * @package WooCommerce\Payments\MultiCurrency\Interfaces
 */

namespace WCPay\MultiCurrency\Interfaces;

defined( 'ABSPATH' ) || exit;

interface MultiCurrencyLocalizationInterface {

	/**
	 * Retrieves the currency's format from mapped data.
	 *
	 * @param string $currency_code The currency code.
	 *
	 * @return array The currency's format.
	 */
	public function get_currency_format( $currency_code ): array;

	/**
	 * Returns the locale data for a country.
	 *
	 * @param string $country Country code.
	 *
	 * @return array Array with the country's locale data. Empty array if country not found.
	 */
	public function get_country_locale_data( $country ): array;
}
