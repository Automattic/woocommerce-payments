<?php
/**
 * WooCommerce Payments Multi Currency Backend Currencies
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\MultiCurrency;

use WC_Payments_Localization_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Class that formats Multi Currency currencies on the backend.
 */
class BackendCurrencies {
	/**
	 * Multi-Currency instance.
	 *
	 * @var MultiCurrency
	 */
	protected $multi_currency;

	/**
	 * WC_Payments_Localization_Service instance.
	 *
	 * @var WC_Payments_Localization_Service
	 */
	protected $localization_service;

	/**
	 * Multi-Currency currency formatting map.
	 *
	 * @var array
	 */
	protected $currency_format = [];

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency                    $multi_currency       The MultiCurrency instance.
	 * @param WC_Payments_Localization_Service $localization_service The Localization Service instance.
	 */
	public function __construct( MultiCurrency $multi_currency, WC_Payments_Localization_Service $localization_service ) {
		$this->multi_currency       = $multi_currency;
		$this->localization_service = $localization_service;

		// We need to check first if it's a request coming from the backend, frontend REST requests shouldn't be
		// affected by this.
		$is_backend_request = 0 === strpos( strtolower( wp_get_referer() ), strtolower( admin_url() ) );

		// Add the filter if it's an admin request or a REST request from the admin side.
		if ( ( is_admin() || ( WC()->is_rest_api_request() && $is_backend_request ) ) && ! defined( 'DOING_CRON' ) ) {
			// Currency hooks. Be aware that this should not run after Explicit Price hook, its priority should be less
			// than explicit price hooks to run before them.
			add_filter( 'wc_price_args', [ $this, 'build_wc_price_args' ], 50 );
			add_filter( 'woocommerce_format_localized_price', [ $this, 'force_decimal_places_in_value' ], 50 );
		}
	}

	/**
	 * Returns the store currency code.
	 *
	 * @return string The store currency code
	 */
	public function get_store_currency(): string {
		return get_woocommerce_currency();
	}

	/**
	 * Returns the currency code to be used by the formatter.
	 *
	 * @param array $args The arguments containing the currency code.
	 *
	 * @return string The code of the currency to be used.
	 */
	public function get_price_currency( $args ): string {
		return $args['currency'] ?? $this->get_store_currency();
	}

	/**
	 * Returns the number of decimals to be used by the formatter.
	 *
	 * @param string $currency_code The currency code to fetch formatting specs.
	 *
	 * @return int The number of decimals.
	 */
	public function get_price_decimals( $currency_code ): int {
		return absint( $this->localization_service->get_currency_format( $currency_code )['num_decimals'] );
	}

	/**
	 * Returns if the currency is a zero decimal point currency.
	 *
	 * @param string $currency_code The currency code to fetch formatting specs.
	 *
	 * @return bool Whether the currency is a zero decimal point currency or not.
	 */
	public function is_zero_decimal_currency( $currency_code ): bool {
		return 0 === $this->get_price_decimals( $currency_code );
	}

	/**
	 * Returns the decimal separator to be used by the formatter.
	 *
	 * @param string $currency_code The currency code to fetch formatting specs.
	 *
	 * @return string The decimal separator.
	 */
	public function get_price_decimal_separator( $currency_code ): string {
		return $this->localization_service->get_currency_format( $currency_code )['decimal_sep'];
	}

	/**
	 * Returns the thousand separator to be used by the formatter.
	 *
	 * @param string $currency_code The currency code to fetch formatting specs.
	 *
	 * @return string The thousand separator.
	 */
	public function get_price_thousand_separator( $currency_code ): string {
		return $this->localization_service->get_currency_format( $currency_code )['thousand_sep'];
	}

	/**
	 * Returns the currency format to be used by the formatter.
	 *
	 * @param string $currency_code The currency code to fetch formatting specs.
	 *
	 * @return string The currency format.
	 */
	public function get_woocommerce_price_format( $currency_code ): string {
		$currency_pos = $this->localization_service->get_currency_format( $currency_code )['currency_pos'];

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
	 * Returns the formatting arguments for the given currency using the locale-info
	 *
	 * @param   array $args  Currency formatter original arguments.
	 *
	 * @return  array         New arguments matching with the locale-info
	 */
	public function build_wc_price_args( $args ) {
		$currency_code = $this->get_price_currency( $args );

		return wp_parse_args(
			[
				'currency'           => $currency_code,
				'decimal_separator'  => $this->get_price_decimal_separator( $this->get_store_currency() ),
				'thousand_separator' => $this->get_price_thousand_separator( $this->get_store_currency() ),
				'decimals'           => $this->is_zero_decimal_currency( $currency_code ) ? 0 : $this->get_price_decimals( $currency_code ),
				'price_format'       => $this->get_woocommerce_price_format( $currency_code ),
			],
			$args
		);
	}

	/**
	 * Enforces the decimal places to a string representation of a price
	 *
	 * @param   string $value   String representation of a price.
	 *
	 * @return  string          Value with fixed decimal places.
	 */
	public function force_decimal_places_in_value( $value ) {

		// Get the settings.
		$decimal_separator = wc_get_price_decimal_separator();
		$decimals          = wc_get_price_decimals();

		// If the decimal point isn't included in the price, skip it.
		if ( false !== strpos( $value, $decimal_separator ) ) {
			$value                    = strval( $value );
			list($wholes, $fractions) = explode( $decimal_separator, $value );
			$new_value                = $wholes . $decimal_separator . substr( str_pad( $fractions, $decimals, '0', STR_PAD_RIGHT ), 0, 2 );
			return $new_value;
		}
		return $value;

	}
}
