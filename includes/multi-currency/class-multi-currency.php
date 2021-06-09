<?php
/**
 * Class Multi_Currency
 *
 * @package WooCommerce\Payments\Multi_Currency
 */

namespace WCPay\Multi_Currency;

use WC_Payments;
use WC_Payments_API_Client;
use WCPay\Exceptions\API_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi Currency functionality.
 */
class Multi_Currency {

	const CURRENCY_SESSION_KEY     = 'wcpay_currency';
	const CURRENCY_META_KEY        = 'wcpay_currency';
	const CURRENCY_CACHE_OPTION    = 'wcpay_multi_currency_cached_currencies';
	const CURRENCY_RETRIEVAL_ERROR = 'error';

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
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

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
			self::$instance = new self( WC_Payments::get_payments_api_client() );
		}
		return self::$instance;
	}

	/**
	 * Class constructor.
	 *
	 * @param WC_Payments_API_Client $payments_api_client Payments API client.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client ) {
		$this->payments_api_client = $payments_api_client;
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

		$this->frontend_prices     = new Frontend_Prices( $this );
		$this->frontend_currencies = new Frontend_Currencies( $this );

		$is_frontend_request = ! is_admin() && ! defined( 'DOING_CRON' ) && ! WC()->is_rest_api_request();

		if ( $is_frontend_request ) {
			add_action( 'init', [ $this, 'update_selected_currency_by_url' ] );
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		if ( is_admin() ) {
			add_filter( 'woocommerce_get_settings_pages', [ $this, 'init_settings_pages' ] );
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
	 * Initialize the Settings Pages.
	 *
	 * @param array $settings_pages The settings pages.
	 *
	 * @return array The new settings pages.
	 */
	public function init_settings_pages( $settings_pages ) {
		include_once WCPAY_ABSPATH . 'includes/multi-currency/class-settings.php';

		$settings_pages[] = new Settings( $this );
		return $settings_pages;
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
	 * Wipes the cached currency data option, forcing to re-fetch the data from WPCOM.
	 *
	 * @return void
	 */
	public function clear_cache() {
		delete_option( self::CURRENCY_CACHE_OPTION );
	}

	/**
	 * Gets and caches the data for the currency rates from the server.
	 * Will be returned as an array with three keys, 'currencies' (the currencies), 'expires' (the expiry time)
	 * and 'updated' (when this data was fetched from the API).
	 *
	 * @return bool|array
	 */
	public function get_cached_currencies() {
		if ( ! $this->payments_api_client->is_server_connected() ) {
			return false;
		}

		$currencies = $this->read_currencies_from_cache();

		// If an array of currencies was returned from the cache, return it here.
		if ( false !== $currencies ) {
			return $currencies;
		}

		// If the option contains the error value, return false early and do not attempt another API call.
		if ( self::CURRENCY_RETRIEVAL_ERROR === $currencies ) {
			return false;
		}

		// If the cache was expired or something went wrong, make a call to the server to get the
		// currency data.
		try {
			$currency_from = get_woocommerce_currency();
			$currencies_to = get_woocommerce_currencies();
			unset( $currencies_to[ $currency_from ] );

			$currency_data = $this->payments_api_client->get_currency_rates(
				$currency_from,
				array_keys( $currencies_to )
			);
		} catch ( API_Exception $e ) {
			// Failed to retrieve currencies from the server. Exception is logged in http client.
			// Rate limit for a short amount of time by caching the failure.
			$this->cache_currencies( self::CURRENCY_RETRIEVAL_ERROR, 1 * MINUTE_IN_SECONDS );

			// Return false to signal currency retrieval error.
			return false;
		}

		$updated = time();

		// Cache the currency data so we don't call the server every time.
		$this->cache_currencies( $currency_data, $updated, 6 * HOUR_IN_SECONDS );

		return [
			'currencies' => $currency_data,
			'updated'    => $updated,
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
	 * Sets up the available currencies, which are alphabetical by name.
	 */
	private function initialize_available_currencies() {
		// Add default store currency with a rate of 1.0.
		$woocommerce_currency                                = get_woocommerce_currency();
		$this->available_currencies[ $woocommerce_currency ] = new Currency( $woocommerce_currency, 1.0 );

		$available_currencies = [];

		$currency_data = $this->get_cached_currencies();
		if ( isset( $currency_data['currencies'] ) && is_array( $currency_data['currencies'] ) ) {
			foreach ( $currency_data['currencies'] as $currency_code => $currency_rate ) {
				$new_currency                                      = new Currency( $currency_code, $currency_rate );
				$available_currencies[ $new_currency->get_name() ] = $new_currency;
			}
		}

		ksort( $available_currencies );

		foreach ( $available_currencies as $currency ) {
			$this->available_currencies[ $currency->get_code() ] = $currency;
		}
	}

	/**
	 * Sets up the enabled currencies.
	 */
	private function initialize_enabled_currencies() {
		$available_currencies     = $this->get_available_currencies();
		$enabled_currency_codes   = get_option( $this->id . '_enabled_currencies', [] );
		$default_code             = $this->get_default_currency()->get_code();
		$enabled_currency_codes[] = $default_code;

		// This allows to keep the alphabetical sorting by name.
		$enabled_currencies = array_filter(
			$available_currencies,
			function( $currency ) use ( $enabled_currency_codes ) {
				return in_array( $currency->get_code(), $enabled_currency_codes, true );
			}
		);

		foreach ( $enabled_currencies as $enabled_currency ) {
			// Get the charm and rounding for each enabled currency and add the currencies to the object property.
			$currency = clone $enabled_currency;
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

			$this->enabled_currencies[ $currency->get_code() ] = $currency;
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
		$supported_types = [ 'product', 'shipping', 'tax', 'coupon' ];
		$currency        = $this->get_selected_currency();

		if ( ! in_array( $type, $supported_types, true ) || $currency->get_is_default() ) {
			return (float) $price;
		}

		$converted_price = ( (float) $price ) * $currency->get_rate();

		if ( 'tax' === $type || 'coupon' === $type ) {
			return $converted_price;
		}

		$charm_compatible_types = [ 'product', 'shipping' ];
		$apply_charm_pricing    = $this->get_apply_charm_only_to_products()
			? 'product' === $type
			: in_array( $type, $charm_compatible_types, true );

		return $this->get_adjusted_price( $converted_price, $apply_charm_pricing, $currency );
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
	 * @param float    $price               The price to be adjusted.
	 * @param bool     $apply_charm_pricing Whether charm pricing should be applied.
	 * @param Currency $currency The currency to be used when adjusting.
	 *
	 * @return float The adjusted price.
	 */
	protected function get_adjusted_price( $price, $apply_charm_pricing, $currency ): float {
		if ( 'none' !== $currency->get_rounding() ) {
			$price = $this->ceil_price( $price, intval( $currency->get_rounding() ) );
		}

		if ( $apply_charm_pricing ) {
			$price += floatval( $currency->get_charm() );
		}

		// Do not return negative prices (possible because of $currency->get_charm()).
		return max( 0, $price );
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

	/**
	 * Caches currency data for a period of time.
	 *
	 * @param string|array $currencies - Currency data to cache.
	 * @param int|null     $updated    - The time the data was fetched from the server.
	 * @param int|null     $expiration - The length of time to cache the currency data, in seconds.
	 *
	 * @return bool
	 */
	private function cache_currencies( $currencies, int $updated = null, int $expiration = null ) {
		// Default expiration to 6 hours if not set.
		if ( null === $expiration ) {
			$expiration = 6 * HOUR_IN_SECONDS;
		}

		// Default updated to the currenct time.
		if ( null === $updated ) {
			$updated = time();
		}

		// Add the currency data, expiry time, and time updated to the array we're caching.
		$currency_cache = [
			'currencies' => $currencies,
			'expires'    => time() + $expiration,
			'updated'    => $updated,
		];

		// Create or update the currency option cache.
		if ( false === get_option( self::CURRENCY_CACHE_OPTION ) ) {
			$result = add_option( self::CURRENCY_CACHE_OPTION, $currency_cache, '', 'no' );
		} else {
			$result = update_option( self::CURRENCY_CACHE_OPTION, $currency_cache, 'no' );
		}

		return $result;
	}

	/**
	 * Read the currency data from the WP option we cache it in.
	 *
	 * @return array|bool
	 */
	private function read_currencies_from_cache() {
		$currency_cache = get_option( self::CURRENCY_CACHE_OPTION );

		if ( false === $currency_cache || ! isset( $currency_cache['currencies'] ) || ! isset( $currency_cache['expires'] ) || ! isset( $currency_cache['updated'] ) ) {
			// No option found or the data isn't in the format we expect.
			return false;
		}

		// Return false if the cache has expired, triggering another fetch.
		if ( $currency_cache['expires'] < time() ) {
			return false;
		}

		// We have fresh currency data in the cache, so return it.
		return $currency_cache;
	}
}
