<?php
/**
 * Class Multi_Currency
 *
 * @package WooCommerce\Payments\Multi_Currency
 */

namespace WCPay\Multi_Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi Currency functionality.
 */
class Multi_Currency {

	const CURRENCY_SESSION_KEY = 'wcpay_currency';

	/**
	 * The single instance of the class.
	 *
	 * @var Multi_Currency
	 */
	protected static $instance = null;

	/**
	 * Frontend_Prices instance.
	 *
	 * @var Frontend_Prices
	 */
	protected $frontend_prices;

	/**
	 * Frontend_Currencies instance.
	 *
	 * @var Frontend_Currencies
	 */
	protected $frontend_currencies;

	/**
	 * The available currencies.
	 *
	 * @var array
	 */
	protected $available_currencies;

	/**
	 * The default currency.
	 *
	 * @var object
	 */
	protected $default_currency;

	/**
	 * The enabled currencies.
	 *
	 * @var array
	 */
	protected $enabled_currencies;

	/**
	 * Main Multi_Currency Instance.
	 *
	 * Ensures only one instance of Multi_Currency is loaded or can be loaded.
	 *
	 * @static
	 * @return Multi_Currency - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Init.
	 */
	public function init() {
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-currency.php';
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-country-flags.php';
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-currency-switcher-widget.php';
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-user-settings.php';
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-frontend-prices.php';
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-frontend-currencies.php';

		$this->id = 'wcpay_multi_currency';
		$this->get_available_currencies();
		$this->get_default_currency();
		$this->get_enabled_currencies();

		add_action( 'rest_api_init', [ __CLASS__, 'init_rest_api' ] );
		add_action(
			'widgets_init',
			function() {
				register_widget( new Currency_Switcher_Widget( $this ) );
			}
		);
		new User_Settings( $this );

		$is_frontend_request = ! is_admin() && ! defined( 'DOING_CRON' ) && ! WC()->is_rest_api_request();

		if ( $is_frontend_request ) {
			add_action( 'init', [ $this, 'update_selected_currency_by_url' ] );

			$this->frontend_prices     = new Frontend_Prices( $this );
			$this->frontend_currencies = new Frontend_Currencies( $this );
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		if ( is_admin() ) {
			// Multi-currency settings page.
			add_filter(
				'woocommerce_get_settings_pages',
				function( $settings_pages ) {
					$settings_pages[] = include_once WCPAY_ABSPATH . 'includes/multi-currency/class-settings.php';
					return $settings_pages;
				}
			);
		}
	}

	/**
	 * Initialize the REST API controller.
	 */
	public static function init_rest_api() {
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-wc-rest-controller.php';
		$api_controller = new WC_REST_Controller( \WC_Payments::create_api_client() );
		$api_controller->register_routes();
	}

	/**
	 * Register the CSS and JS scripts.
	 */
	public function register_scripts() {
		$script_src_url    = plugins_url( 'dist/multi-currency.js', WCPAY_PLUGIN_FILE );
		$script_asset_path = WCPAY_ABSPATH . 'dist/multi-currency.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require_once $script_asset_path : [ 'dependencies' => [] ];
		wp_register_script(
			'WCPAY_MULTI_CURRENCY_SETTINGS',
			$script_src_url,
			$script_asset['dependencies'],
			\WC_Payments::get_file_version( 'dist/multi-currency.js' ),
			true
		);

		wp_localize_script(
			'WCPAY_MULTI_CURRENCY_SETTINGS',
			'wcpayMultiCurrencySettings',
			[
				'enabledCurrencies'   => $this->get_enabled_currencies_for_settings(),
				'availableCurrencies' => $this->get_available_currencies_for_settings(),
				'defaultCurrency'     => $this->get_default_currency(),
			]
		);

		/*
		TODO: Add styling.
		// @codingStandardsIgnoreStart
		wp_register_style(
			'WCPAY_MULTI_CURRENCY_SETTINGS',
			plugins_url( 'dist/multi-currency.css', WCPAY_PLUGIN_FILE ),
			[ 'wc-components' ],
			WC_Payments::get_file_version( 'dist/multi-currency.css' )
		);
		// @codingStandardsIgnoreEnd
		*/
	}

	/**
	 * Load the assets.
	 */
	public function enqueue_scripts() {
		global $current_tab, $current_section;

		$this->register_scripts();

		// TODO: Set this to only display when needed.
		// Output the settings JS and CSS only on the settings page.
		wp_enqueue_script( 'WCPAY_MULTI_CURRENCY_SETTINGS' );
		wp_enqueue_style( 'WCPAY_MULTI_CURRENCY_SETTINGS' );
	}

	/**
	 * Gets the mock available.
	 *
	 * @return array Array of currencies.
	 */
	public function get_mock_currencies() {
		return [
			[ 'USD', '1.00' ],
			[ 'CAD', '1.206823' ],
			[ 'GBP', '0.708099' ],
			[ 'EUR', '0.826381' ],
			[ 'AED', '3.6732' ],
			[ 'CDF', '2000' ],
			[ 'NZD', '1.387163' ],
			[ 'DKK', '6.144615' ],
			[ 'BIF', '1974' ], // Zero dollar currency.
			[ 'CLP', '706.8' ], // Zero dollar currency.
		];
	}

	/**
	 * Gets the currencies available.
	 *
	 * @return array Array of Currency objects.
	 */
	public function get_available_currencies() {
		if ( isset( $this->available_currencies ) ) {
			return $this->available_currencies;
		}

		// TODO: This will need to get stored data, then build and return it accordingly.
		$currencies = $this->get_mock_currencies();
		foreach ( $currencies as $currency ) {
			$this->available_currencies[ $currency[0] ] = new Currency( $currency[0], $currency[1] );
		}
		return $this->available_currencies;
	}

	/**
	 * Gets the store base currency.
	 *
	 * @return Currency The store base currency.
	 */
	public function get_default_currency(): Currency {
		if ( isset( $this->default_currency ) ) {
			return $this->default_currency;
		}

		$this->default_currency = $this->available_currencies[ get_woocommerce_currency() ];

		return $this->default_currency;
	}

	/**
	 * Gets the currently enabled currencies.
	 *
	 * @return array Array of Currency objects.
	 */
	public function get_enabled_currencies() {
		if ( isset( $this->enabled_currencies ) ) {
			return $this->enabled_currencies;
		}

		$this->enabled_currencies = get_option( $this->id . '_enabled_currencies', false );
		if ( ! $this->enabled_currencies ) {

			// TODO: Remove dev mode option here.
			if ( get_option( 'wcpaydev_dev_mode', false ) ) {
				$count = 0;
				foreach ( $this->available_currencies as $currency ) {
					$this->enabled_currencies[ $currency->code ] = $currency;
					if ( $count >= 3 ) {
						break;
					}
					$count++;
				}
			} else {
				$default = $this->get_default_currency();
				// Need to set the default as an array.
				$this->enabled_currencies[ $default->code ] = $default;
			}
		}

		return $this->enabled_currencies;
	}

	/**
	 * Gets the user selected currency, or `$default_currency` if is not set.
	 *
	 * @return Currency
	 */
	public function get_selected_currency(): Currency {
		if ( WC()->session ) {
			$code = WC()->session->get( self::CURRENCY_SESSION_KEY );
			return $this->get_enabled_currencies()[ $code ] ?? $this->default_currency;
		}

		return $this->default_currency;
	}

	/**
	 * Update the selected currency from a currency code.
	 *
	 * @param string $currency_code Three letter currency code.
	 * @return void
	 */
	public function update_selected_currency( string $currency_code ) {
		$code     = strtoupper( $currency_code );
		$currency = $this->get_enabled_currencies()[ $code ] ?? null;

		if ( $currency && WC()->session ) {
			WC()->session->set( self::CURRENCY_SESSION_KEY, $currency->code );
		}
	}

	/**
	 * Update the selected currency from url param `currency`.
	 *
	 * @return void
	 */
	public function update_selected_currency_by_url() {
		if ( ! isset( $_GET['currency'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$this->update_selected_currency( sanitize_text_field( wp_unslash( $_GET['currency'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Gets the rounding precision in the format used by round().
	 *
	 * @return int The rounding precision.
	 */
	public function get_round_precision(): float {
		return apply_filters( 'wcpay_multi_currency_round_precision', 0 );
	}

	/**
	 * Gets the charm pricing to be added to the converted price after rounding.
	 *
	 * @return float The charm pricing.
	 */
	public function get_charm_pricing(): float {
		return apply_filters( 'wcpay_multi_currency_charm_pricing', -0.1 );
	}

	/**
	 * Gets the configured value for apply charm pricing only to products.
	 *
	 * @return bool The configured value.
	 */
	public function get_apply_charm_only_to_products() {
		return apply_filters( 'wcpay_multi_currency_apply_charm_only_to_products', true );
	}

	/**
	 * Gets the converted price using the current currency with the rounding and charm pricing settings.
	 *
	 * @param mixed $price The price to be converted.
	 * @param bool  $type  The type of price being converted. One of 'product', 'shipping', 'tax', or 'coupon'.
	 *
	 * @return float The converted price.
	 */
	public function get_price( $price, $type ): float {
		$supported_types  = [ 'product', 'shipping', 'tax', 'coupon' ];
		$current_currency = $this->get_selected_currency();

		if (
			! in_array( $type, $supported_types, true ) ||
			$current_currency->get_code() === $this->get_default_currency()->get_code()
		) {
			return (float) $price;
		}

		$converted_price = ( (float) $price ) * $current_currency->get_rate();

		if ( 'tax' === $type || 'coupon' === $type ) {
			return $converted_price;
		}

		$charm_compatible_types = [ 'product', 'shipping' ];
		$apply_charm_pricing    = $this->get_apply_charm_only_to_products()
			? 'product' === $type
			: in_array( $type, $charm_compatible_types, true );

		return $this->get_adjusted_price( $converted_price, $apply_charm_pricing );
	}

	/**
	 * Gets the price after adjusting it with the rounding and charm settings.
	 *
	 * @param float $price               The price to be adjusted.
	 * @param bool  $apply_charm_pricing Whether charm pricing should be applied.
	 *
	 * @return float The adjusted price.
	 */
	protected function get_adjusted_price( $price, $apply_charm_pricing ): float {
		$precision = $this->get_round_precision();
		$charm     = $this->get_charm_pricing();

		$adjusted_price = $this->ceil_price( $price, $precision );

		if ( $apply_charm_pricing ) {
			$adjusted_price += $charm;
		}

		// Do not return negative prices (possible because of $charm).
		return max( 0, $adjusted_price );
	}

	/**
	 * Ceils the price to the next number based on the precision.
	 *
	 * @param float $price     The price to be ceiled.
	 * @param int   $precision The precision to be used.
	 *
	 * @return float The ceiled price.
	 */
	protected function ceil_price( $price, $precision ) {
		$precision_modifier = pow( 10, $precision );
		return ceil( $price * $precision_modifier ) / $precision_modifier;
	}

	/**
	 * Gets currencies for settings pages.
	 *
	 * @param string $type The type of currencies to return.
	 *
	 * @return array Array of arrays of currencies, defaults to enabled.
	 */
	private function get_currencies_for_settings( $type = '' ) {
		switch ( $type ) {
			case 'available':
				$currencies = $this->get_available_currencies();
				break;
			default:
				$currencies = $this->get_enabled_currencies();
		}

		// Set the name to the main key for sorting purposes.
		foreach ( $currencies as $currency ) {
			$list[ $currency->get_name() ] = [
				'code' => $currency->get_code(),
				'name' => $currency->get_name(),
				'flag' => $currency->get_flag(),
			];
		}
		ksort( $list );

		// Now that we are sorted, drop the name and use the code as the key.
		foreach ( $list as $currency ) {
			$return[ $currency['code'] ] = $currency;
		}

		// Set default currency to the top of the list.
		$default_currency                         = $this->get_default_currency();
		$default[ $default_currency->get_code() ] = $return[ $default_currency->get_code() ];
		unset( $return[ $default_currency->get_code() ] );
		$return = array_merge( $default, $return );

		return $return;
	}

	/**
	 * Gets the available currencies for the settings pages.
	 *
	 * @return array Array of arrays of available currencies.
	 */
	public function get_available_currencies_for_settings() {
		return $this->get_currencies_for_settings( 'available' );
	}

	/**
	 * Gets the enabled currencies for the settings pages.
	 *
	 * @return array Array of arrays of enabled currencies.
	 */
	public function get_enabled_currencies_for_settings() {
		return $this->get_currencies_for_settings( 'enabled' );
	}
}
