<?php
/**
 * WooCommerce Payments WC_Payments_Localization_Service Class
 *
 * @package WooCommerce\Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Payments_Localization_Service.
 */
class WC_Payments_Localization_Service {
	const WCPAY_CURRENCY_FORMAT_TRANSIENT = 'wcpay_currency_format';
	const WCPAY_LOCALE_INFO_TRANSIENT     = 'wcpay_locale_info';

	/**
	 * Currency formatting map.
	 *
	 * @var array
	 */
	protected $currency_format = [];

	/**
	 * Currency locale info.
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
	 * Retrieves the currency's format from mapped data.
	 *
	 * @param string $currency_code The currency code.
	 *
	 * @return array The currency's format.
	 */
	public function get_currency_format( $currency_code ): array {
		// Default to USD settings if mapping not found.
		$currency_format = [
			'currency_pos' => 'left',
			'thousand_sep' => ',',
			'decimal_sep'  => '.',
			'num_decimals' => 2,
		];

		$locale = $this->get_user_locale();

		$currency_options = $this->currency_format[ $currency_code ] ?? null;
		if ( $currency_options ) {
			// If there's no locale-specific formatting, default to the 'default' entry in the array.
			$currency_format = $currency_options[ $locale ] ?? $currency_options['default'] ?? $currency_format;
		}

		/**
		 * Filter to edit formatting for a specific currency (wcpay_{currency_code}_format).
		 *
		 * This filter can be used to override the currency format for a specific currency.
		 * The currency code in the filter name should be used in lowercase.
		 *
		 * @since 2.8.0
		 *
		 * @param array  $currency_format The currency format settings.
		 * @param string $locale          The user's locale.
		 */
		return apply_filters( 'wcpay_' . strtolower( $currency_code ) . '_format', $currency_format, $locale );
	}

	/**
	 * Returns the user locale.
	 *
	 * @return string The locale.
	 */
	public function get_user_locale(): string {
		return get_user_locale();
	}

	// TODO: Add tests.
	/**
	 * Returns the locale data for a country.
	 *
	 * @param string $country Country code.
	 *
	 * @return array Array with the country's locale data. Empty array if country not found.
	 */
	public function get_country_locale_data( $country ): array {
		return $this->locale_info[ $country ] ?? [];
	}

	/**
	 * Loads locale data from WooCommerce core (/i18n/locale-info.php) and maps it
	 * to be used by currency.
	 *
	 * @return void
	 */
	private function load_locale_data() {
		$transient_currency_format_data = get_transient( self::WCPAY_CURRENCY_FORMAT_TRANSIENT );
		$transient_locale_info_data     = get_transient( self::WCPAY_LOCALE_INFO_TRANSIENT );

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

			set_transient( self::WCPAY_CURRENCY_FORMAT_TRANSIENT, $this->currency_format, DAY_IN_SECONDS );
			set_transient( self::WCPAY_LOCALE_INFO_TRANSIENT, $this->locale_info, DAY_IN_SECONDS );
		}
	}
}
