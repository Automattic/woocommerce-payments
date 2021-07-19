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
	 * Constructor.
	 */
	public function __construct() {
		$this->load_locale_data();
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
		$transient_name = 'wcpay_multi_currency_locale_data';
		$transient_data = get_transient( $transient_name );
		if ( $transient_data ) {
			$this->currency_format = $transient_data;
			return;
		}

		$locale_info_path = WC()->plugin_path() . '/i18n/locale-info.php';

		// The full locale data was introduced in the currency-info.php file.
		// If it doesn't exist we have to use the fallback.
		if ( ! file_exists( WC()->plugin_path() . '/i18n/currency-info.php' ) ) {
			$locale_info_path = WCPAY_ABSPATH . 'i18n/locale-info.php';
		}

		$locale_info = include $locale_info_path;

		if ( is_array( $locale_info ) && 0 < count( $locale_info ) ) {
			// Extract the currency formatting options from the locale info.
			foreach ( $locale_info as $country_data ) {
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

			set_transient( $transient_name, $this->currency_format, DAY_IN_SECONDS );
		}
	}
}
