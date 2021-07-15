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
	 * Returns the user locale country.
	 *
	 * @return string The country code.
	 */
	public function get_user_locale_country(): string {
		$locale = explode( '_', get_user_locale() );
		return end( $locale );
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

		$locale_info = include WC()->plugin_path() . '/i18n/locale-info.php';

		if ( is_array( $locale_info ) && 0 < count( $locale_info ) ) {
			$countries          = array_keys( $locale_info );
			$first_country_data = $locale_info[ $countries[0] ];

			// If the loaded locale_info doesn't contain the locales keys, load the fallback file.
			if ( is_array( $first_country_data ) && ! array_key_exists( 'locales', $first_country_data ) ) {
				$locale_info = include WCPAY_ABSPATH . 'i18n/locale-info.php';
			}

			// Extract the currency formatting options from the locale info.
			foreach ( $locale_info as $country => $locale ) {
				$currency_code = $locale['currency_code'];

				// Convert Norwegian Krone symbol to its ISO 4217 currency code.
				if ( 'Kr' === $currency_code ) {
					$currency_code = 'NOK';
				}

				$this->currency_format[ $currency_code ][ $country ] = [
					'currency_pos' => $locale['currency_pos'],
					'thousand_sep' => $locale['thousand_sep'],
					'decimal_sep'  => $locale['decimal_sep'],
					'num_decimals' => $locale['num_decimals'],
				];
			}

			set_transient( $transient_name, $this->currency_format, DAY_IN_SECONDS );
		}
	}
}
