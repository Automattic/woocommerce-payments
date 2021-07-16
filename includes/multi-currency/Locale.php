<?php
/**
 * WooCommerce Payments Multi Currency Locale Class
 *
 * @package WooCommerce\Admin
 */

namespace WCPay\MultiCurrency;

defined( 'ABSPATH' ) || exit;

/**
 * Locale.
 */
class Locale {
	/**
	 * Multi-Currency currency formatting map.
	 *
	 * @var array
	 */
	protected $currency_format = [];

	/**
	 * Multi-Currency currency locale info.
	 *
	 * @var array
	 */
	protected $locale_info = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->load_locale_data();
	}

	/**
	 * Gets the customer's currency based on their location.
	 *
	 * @return string|null Currency code or null if not found.
	 */
	public function get_currency_by_customer_location() {
		$location = wc_get_customer_default_location();

		return $this->locale_info[ $location['country'] ]['currency_code'] ?? null;
	}

	/**
	 * Gets the currency formatting options.
	 *
	 * @param string $currency_code The 3 letter currency code.
	 *
	 * @return array|bool Returns array of the formatting data if found, false if not.
	 */
	public function get_currency_format( string $currency_code ) {
		if ( ! empty( $this->currency_format[ $currency_code ] ) ) {
			return $this->currency_format[ $currency_code ];
		}
		return false;
	}

	/**
	 * Returns the user locale.
	 *
	 * @return string The locale.
	 */
	public function get_user_locale(): string {
		return get_user_locale();
	}

	/**
	 * Loads locale data from WooCommerce core (/i18n/locale-info.php) and maps it
	 * to be used by currency.
	 *
	 * @return void
	 */
	private function load_locale_data() {
		// Define transient names, get their data, and return that data if it exists.
		$transient_currency_format_name = 'wcpay_multi_currency_currency_format';
		$transient_currency_format_data = get_transient( $transient_currency_format_name );
		$transient_locale_info_name     = 'wcpay_multi_currency_locale_info';
		$transient_locale_info_data     = get_transient( $transient_locale_info_name );

		if ( $transient_currency_format_data && $transient_locale_info_data ) {
			$this->currency_format = $transient_currency_format_data;
			$this->locale_info     = $transient_locale_info_data;
			return;
		}

		$locale_info_path = WC()->plugin_path() . '/i18n/locale-info.php';

		// The full locale data was introduced in the currency-info.php file.
		// If it doesn't exist we have to use the fallback.
		if ( ! file_exists( WC()->plugin_path() . '/i18n/currency-info.php' ) ) {
			$locale_info_path = WCPAY_ABSPATH . 'i18n/locale-info.php';
		}

		$this->locale_info = include $locale_info_path;

		if ( is_array( $this->locale_info ) && 0 < count( $this->locale_info ) ) {
			// Extract the currency formatting options from the locale info.
			foreach ( $this->locale_info as $country_data ) {
				$currency_code = $country_data['currency_code'];

				foreach ( $country_data['locales'] as $locale => $locale_data ) {
					if ( empty( $locale_data ) ) {
						continue;
					}

					$this->currency_format[ $currency_code ][ $locale ] = [
						'currency_pos' => $locale_data['currency_pos'],
						'thousand_sep' => $locale_data['thousand_sep'],
						'decimal_sep'  => $locale_data['decimal_sep'],
						'num_decimals' => $country_data['num_decimals'],
					];
				}
			}

			set_transient( $transient_currency_format_name, $this->currency_format, DAY_IN_SECONDS );
			set_transient( $transient_locale_info_name, $this->locale_info, DAY_IN_SECONDS );
		}
	}
}
