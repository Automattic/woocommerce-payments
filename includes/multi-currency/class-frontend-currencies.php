<?php
/**
 * WooCommerce Payments Multi Currency Frontend Currencies
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Multi_Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that formats Multi Currency currencies on the frontend.
 */
class Frontend_Currencies {
	/**
	 * Multi-Currency instance.
	 *
	 * @var Multi_Currency
	 */
	protected $multi_currency;

	/**
	 * Multi-Currency currency formatting map.
	 *
	 * @var array
	 */
	protected $currency_settings = [];

	/**
	 * Constructor.
	 *
	 * @param Multi_Currency $multi_currency The Multi_Currency instance.
	 */
	public function __construct( Multi_Currency $multi_currency ) {
		$this->multi_currency = $multi_currency;

		$this->load_locale_data();

		// Currency hooks.
		add_filter( 'woocommerce_currency', [ $this, 'get_current_currency_code' ] );
		add_filter( 'wc_get_price_decimals', [ $this, 'get_current_currency_decimals' ] );
		add_filter( 'wc_get_price_decimal_separator', [ $this, 'get_current_currency_decimal_separator' ] );
		add_filter( 'wc_get_price_thousand_separator', [ $this, 'get_current_currency_thousand_separator' ] );
		add_filter( 'woocommerce_price_format', [ $this, 'get_current_currency_format' ] );
	}

	/**
	 * Returns the currency code to be used by WooCommerce.
	 *
	 * @return string The code of the currency to be used.
	 */
	public function get_current_currency_code() {
		return $this->multi_currency->get_selected_currency()->get_code();
	}

	/**
	 * Returns the number of decimals to be used by WooCommerce.
	 *
	 * @return int The number of decimals.
	 */
	public function get_current_currency_decimals() {
		$currency_code = $this->multi_currency->get_selected_currency()->get_code();
		return $this->get_currency_settings( $currency_code )['num_decimals'];
	}

	/**
	 * Returns the decimal separator to be used by WooCommerce.
	 *
	 * @return int The decimal separator.
	 */
	public function get_current_currency_decimal_separator() {
		$currency_code = $this->multi_currency->get_selected_currency()->get_code();
		return $this->get_currency_settings( $currency_code )['decimal_sep'];
	}

	/**
	 * Returns the thousand separator to be used by WooCommerce.
	 *
	 * @return int The thousand separator.
	 */
	public function get_current_currency_thousand_separator() {
		$currency_code = $this->multi_currency->get_selected_currency()->get_code();
		return $this->get_currency_settings( $currency_code )['thousand_sep'];
	}

	/**
	 * Returns the currency format to be used by WooCommerce.
	 *
	 * @return int The currency format.
	 */
	public function get_current_currency_format() {
		$currency_code = $this->multi_currency->get_selected_currency()->get_code();
		$currency_pos  = $this->get_currency_settings( $currency_code )['currency_pos'];

		switch ( $currency_pos ) {
			case 'left':
				return '%1$s%2$s';
			case 'right':
				return '%2$s%1$s';
			case 'left_space':
				return '%1$s&nbsp;%2$s';
			case 'right_space':
				return '%2$s&nbsp;%1$s';
			default:
				return '%1$s%2$s';
		}
	}

	/**
	 * Retrieves the currency's settings from mapped data.
	 *
	 * @param string $currency_code The currency code.
	 *
	 * @return array The currency's settings.
	 */
	private function get_currency_settings( $currency_code ) {
		// Default to USD settings if mapping not found.
		$currency_settings = $this->currency_settings[ $currency_code ] ?? [
			'currency_pos' => 'left',
			'thousand_sep' => ',',
			'decimal_sep'  => '.',
			'num_decimals' => 2,
		];

		return apply_filters( 'wcpay_multi_currency_currency_settings', $currency_settings, $currency_code );
	}

	/**
	 * Loads locale data from WooCommerce core (/i18n/locale-info.php) and maps it
	 * to be used by currency.
	 */
	private function load_locale_data() {
		$locale_info = include WC()->plugin_path() . '/i18n/locale-info.php';

		// Extract the currency formatting options from the locale info.
		foreach ( $locale_info as $locale ) {
			$currency_code = $locale['currency_code'];

			// Convert Norwegian Krone symbol to its ISO 4217 currency code.
			if ( 'Kr' === $currency_code ) {
				$currency_code = 'NOK';
			}

			if ( empty( $this->currency_settings[ $currency_code ] ) ) {
				$this->currency_settings[ $currency_code ] = [
					'currency_pos' => $locale['currency_pos'],
					'thousand_sep' => $locale['thousand_sep'],
					'decimal_sep'  => $locale['decimal_sep'],
					'num_decimals' => $locale['num_decimals'],
				];
			}
		}
	}
}
