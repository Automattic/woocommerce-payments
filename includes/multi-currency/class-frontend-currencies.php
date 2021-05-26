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
	protected $currency_format = [];

	/**
	 * Constructor.
	 *
	 * @param Multi_Currency $multi_currency The Multi_Currency instance.
	 */
	public function __construct( Multi_Currency $multi_currency ) {
		$this->multi_currency = $multi_currency;

		$this->load_locale_data();

		// Currency hooks.
		add_filter( 'woocommerce_currency', [ $this, 'get_woocommerce_currency' ], 50 );
		add_filter( 'wc_get_price_decimals', [ $this, 'get_price_decimals' ], 50 );
		add_filter( 'wc_get_price_decimal_separator', [ $this, 'get_price_decimal_separator' ], 50 );
		add_filter( 'wc_get_price_thousand_separator', [ $this, 'get_price_thousand_separator' ], 50 );
		add_filter( 'woocommerce_price_format', [ $this, 'get_woocommerce_price_format' ], 50 );
	}

	/**
	 * Returns the currency code to be used by WooCommerce.
	 *
	 * @return string The code of the currency to be used.
	 *
	 * @throws Exceptions\Invalid_Default_Currency_Exception If the store's default currency is not available.
	 */
	public function get_woocommerce_currency() {
		return $this->multi_currency->get_selected_currency()->get_code();
	}

	/**
	 * Returns the number of decimals to be used by WooCommerce.
	 *
	 * @return int The number of decimals.
	 *
	 * @throws Exceptions\Invalid_Default_Currency_Exception If the store's default currency is not available.
	 */
	public function get_price_decimals() {
		$currency_code = $this->multi_currency->get_selected_currency()->get_code();
		return $this->get_currency_format( $currency_code )['num_decimals'];
	}

	/**
	 * Returns the decimal separator to be used by WooCommerce.
	 *
	 * @return int The decimal separator.
	 *
	 * @throws Exceptions\Invalid_Default_Currency_Exception If the store's default currency is not available.
	 */
	public function get_price_decimal_separator() {
		$currency_code = $this->multi_currency->get_selected_currency()->get_code();
		return $this->get_currency_format( $currency_code )['decimal_sep'];
	}

	/**
	 * Returns the thousand separator to be used by WooCommerce.
	 *
	 * @return int The thousand separator.
	 *
	 * @throws Exceptions\Invalid_Default_Currency_Exception If the store's default currency is not available.
	 */
	public function get_price_thousand_separator() {
		$currency_code = $this->multi_currency->get_selected_currency()->get_code();
		return $this->get_currency_format( $currency_code )['thousand_sep'];
	}

	/**
	 * Returns the currency format to be used by WooCommerce.
	 *
	 * @return int The currency format.
	 *
	 * @throws Exceptions\Invalid_Default_Currency_Exception If the store's default currency is not available.
	 */
	public function get_woocommerce_price_format() {
		$currency_code = $this->multi_currency->get_selected_currency()->get_code();
		$currency_pos  = $this->get_currency_format( $currency_code )['currency_pos'];

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
	 * Retrieves the currency's format from mapped data.
	 *
	 * @param string $currency_code The currency code.
	 *
	 * @return array The currency's format.
	 */
	private function get_currency_format( $currency_code ) {
		// Default to USD settings if mapping not found.
		$currency_format = $this->currency_format[ $currency_code ] ?? [
			'currency_pos' => 'left',
			'thousand_sep' => ',',
			'decimal_sep'  => '.',
			'num_decimals' => 2,
		];

		return apply_filters( 'wcpay_multi_currency_' . strtolower( $currency_code ) . '_format', $currency_format );
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

			if ( empty( $this->currency_format[ $currency_code ] ) ) {
				$this->currency_format[ $currency_code ] = [
					'currency_pos' => $locale['currency_pos'],
					'thousand_sep' => $locale['thousand_sep'],
					'decimal_sep'  => $locale['decimal_sep'],
					'num_decimals' => $locale['num_decimals'],
				];
			}
		}
	}
}
