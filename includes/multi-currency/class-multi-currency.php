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
	const CURRENCY_META_KEY    = 'wcpay_currency';

	/**
	 * The plugin's ID.
	 *
	 * @var string
	 */
	public $id = 'wcpay_multi_currency';

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
		$this->includes();
		$this->init();
	}

	/**
	 * Init.
	 */
	public function init() {
		$this->initialize_available_currencies();
		$this->set_default_currency();
		$this->initialize_enabled_currencies();

		add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
		add_action( 'widgets_init', [ $this, 'init_widgets' ] );
		new User_Settings( $this );

		$is_frontend_request = ! is_admin() && ! defined( 'DOING_CRON' ) && ! WC()->is_rest_api_request();

		$this->frontend_prices     = new Frontend_Prices( $this );
		$this->frontend_currencies = new Frontend_Currencies( $this );

		if ( $is_frontend_request ) {
			add_action( 'init', [ $this, 'update_selected_currency_by_url' ] );
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
	public function init_rest_api() {
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-wc-rest-controller.php';

		$api_controller = new WC_REST_Controller( \WC_Payments::create_api_client() );
		$api_controller->register_routes();
	}

	/**
	 * Initialize the Widgets.
	 */
	public function init_widgets() {
		register_widget( new Currency_Switcher_Widget( $this ) );
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

		wp_register_style(
			'WCPAY_MULTI_CURRENCY_SETTINGS',
			plugins_url( 'dist/multi-currency.css', WCPAY_PLUGIN_FILE ),
			[ 'wc-components' ],
			\WC_Payments::get_file_version( 'dist/multi-currency.css' )
		);
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
	 * Returns the Frontend_Prices instance.
	 *
	 * @return Frontend_Prices
	 */
	public function get_frontend_prices() {
		return $this->frontend_prices;
	}

	/**
	 * Returns the Frontend_Currencies instance.
	 *
	 * @return Frontend_Currencies
	 */
	public function get_frontend_currencies() {
		return $this->frontend_currencies;
	}

	/**
	 * Sets up the available currencies.
	 */
	private function initialize_available_currencies() {
		// Add default store currency with a rate of 1.0.
		$woocommerce_currency                                = get_woocommerce_currency();
		$this->available_currencies[ $woocommerce_currency ] = new Currency( $woocommerce_currency, 1.0 );

		// TODO: This will need to get stored data, then build and return it accordingly.
		$currencies = $this->get_mock_currencies();
		foreach ( $currencies as $currency ) {
			$this->available_currencies[ $currency[0] ] = new Currency( $currency[0], $currency[1] );
		}
	}

	/**
	 * Sets up the enabled currencies.
	 */
	private function initialize_enabled_currencies() {
		$available_currencies = $this->get_available_currencies();
		$enabled_currencies   = get_option( $this->id . '_enabled_currencies', false );
		$default_code         = $this->get_default_currency()->get_code();

		if ( ! $enabled_currencies ) {
			// TODO: Remove dev mode option here.
			if ( get_option( 'wcpaydev_dev_mode', false ) ) {
				$count = 0;
				foreach ( $available_currencies as $currency ) {
					$enabled_currencies[] = $currency->get_code();
					if ( $count >= 3 ) {
						break;
					}
					$count++;
				}
			} else {
				$enabled_currencies[] = $default_code;
			}
		}

		foreach ( $enabled_currencies as $code ) {
			// Get the charm and rounding for each enabled currency and add the currencies to the object property.
			$currency = clone $available_currencies[ $code ];
			$charm    = get_option( $this->id . '_price_charm_' . $currency->get_id(), 0.00 );
			$rounding = get_option( $this->id . '_price_rounding_' . $currency->get_id(), 'none' );
			$currency->set_charm( $charm );
			$currency->set_rounding( $rounding );

			// If the currency is set to be manual, set the rate to the stored manual rate.
			$type = get_option( $this->id . '_exchange_rate_' . $currency->get_id(), 'automatic' );
			if ( 'manual' === $type ) {
				$manual_rate = get_option( $this->id . '_manual_rate_' . $currency->get_id(), $currency->get_rate() );
				$currency->set_rate( $manual_rate );
			}

			$this->enabled_currencies[ $code ] = $currency;
		}

		// Set default currency to the top of the list.
		$default[ $default_code ] = $this->enabled_currencies[ $default_code ];
		unset( $this->enabled_currencies[ $default_code ] );
		$this->enabled_currencies = array_merge( $default, $this->enabled_currencies );
	}

	/**
	 * Sets the default currency.
	 */
	private function set_default_currency() {
		$this->default_currency = $this->available_currencies[ get_woocommerce_currency() ] ?? null;
	}

	/**
	 * Gets the currencies available.
	 *
	 * @return array Array of Currency objects.
	 */
	public function get_available_currencies(): array {
		return $this->available_currencies;
	}

	/**
	 * Gets the store base currency.
	 *
	 * @return Currency The store base currency.
	 */
	public function get_default_currency(): Currency {
		return $this->default_currency;
	}

	/**
	 * Gets the currently enabled currencies.
	 *
	 * @return array Array of Currency objects.
	 */
	public function get_enabled_currencies(): array {
		return $this->enabled_currencies;
	}

	/**
	 * Gets the user selected currency, or `$default_currency` if is not set.
	 *
	 * @return Currency
	 */
	public function get_selected_currency(): Currency {
		$user_id = get_current_user_id();
		$code    = null;

		if ( 0 === $user_id && WC()->session ) {
			$code = WC()->session->get( self::CURRENCY_SESSION_KEY );
		} elseif ( $user_id ) {
			$code = get_user_meta( $user_id, self::CURRENCY_META_KEY, true );
		}

		return $this->get_enabled_currencies()[ $code ] ?? $this->default_currency;
	}

	/**
	 * Update the selected currency from a currency code.
	 *
	 * @param string $currency_code Three letter currency code.
	 * @return void
	 */
	public function update_selected_currency( string $currency_code ) {
		$code     = strtoupper( $currency_code );
		$user_id  = get_current_user_id();
		$currency = $this->get_enabled_currencies()[ $code ] ?? null;

		if ( null === $currency ) {
			return;
		}

		if ( 0 === $user_id && WC()->session ) {
			WC()->session->set( self::CURRENCY_SESSION_KEY, $currency->get_code() );
			// Set the session cookie if is not yet to persist the selected currency.
			if ( ! WC()->session->has_session() && ! headers_sent() ) {
				WC()->session->set_customer_session_cookie( true );
			}
		} elseif ( $user_id ) {
			update_user_meta( $user_id, self::CURRENCY_META_KEY, $currency->get_code() );
		}
	}

	/**
	 * Sets the enabled currencies for the store.
	 *
	 * @param array $currencies Array of currency codes to be enabled.
	 */
	public function set_enabled_currencies( $currencies = [] ) {
		if ( 0 < count( $currencies ) ) {
			update_option( $this->id . '_enabled_currencies', $currencies );
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

		// Recalculate cart when currency changes.
		add_action( 'wp_loaded', [ $this, 'recalculate_cart' ] );
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
	 * Recalculates WooCommerce cart totals.
	 */
	public function recalculate_cart() {
		WC()->cart->calculate_totals();
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
	 * Include required core files used in admin and on the frontend.
	 */
	protected function includes() {
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-currency.php';
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-currency-switcher-widget.php';
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-country-flags.php';
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-frontend-prices.php';
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-frontend-currencies.php';
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-user-settings.php';
	}
}
