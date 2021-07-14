<?php
/**
 * Class MultiCurrency
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency;

use WC_Payments;
use WC_Payments_API_Client;
use WCPay\Exceptions\API_Exception;
use WCPay\MultiCurrency\Notes\NoteMultiCurrencyAvailable;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi Currency functionality.
 */
class MultiCurrency {

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
	 * @var ?MultiCurrency
	 */
	protected static $instance = null;

	/**
	 * Compatibility instance.
	 *
	 * @var Compatibility
	 */
	protected $compatibility;

	/**
	 * Utils instance.
	 *
	 * @var Utils
	 */
	protected $utils;

	/**
	 * FrontendPrices instance.
	 *
	 * @var FrontendPrices
	 */
	protected $frontend_prices;

	/**
	 * FrontendCurrencies instance.
	 *
	 * @var FrontendCurrencies
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
	 * @var Currency
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
	 * Main MultiCurrency Instance.
	 *
	 * Ensures only one instance of MultiCurrency is loaded or can be loaded.
	 *
	 * @static
	 * @return MultiCurrency - Main instance.
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
		$this->utils               = new Utils();
		$this->compatibility       = new Compatibility( $this->utils );

		add_action( 'init', [ $this, 'init' ] );
		add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
		add_action( 'widgets_init', [ $this, 'init_widgets' ] );

		$is_frontend_request = ! is_admin() && ! defined( 'DOING_CRON' ) && ! WC()->is_rest_api_request();

		if ( $is_frontend_request ) {
			// Make sure that this runs after the main init function.
			add_action( 'init', [ $this, 'update_selected_currency_by_url' ], 11 );
		}
	}

	/**
	 * Called after the WooCommerce session has been initialized. Initialises the available currencies,
	 * default currency and enabled currencies for the multi currency plugin.
	 *
	 * @return void
	 */
	public function init() {
		$store_currency_updated = $this->check_store_currency_for_change();

		// If the store currency has been updated, clear the cache to make sure we fetch fresh rates from the server.
		if ( $store_currency_updated ) {
			$this->clear_cache();
		}

		$this->initialize_available_currencies();
		$this->set_default_currency();
		$this->initialize_enabled_currencies();

		// If the store currency has been updated, we need to update the notice that will display any manual currencies.
		if ( $store_currency_updated ) {
			$this->update_manual_rate_currencies_notice_option();
		}

		new AdminNotices();
		new UserSettings( $this );

		$this->frontend_prices     = new FrontendPrices( $this, $this->compatibility );
		$this->frontend_currencies = new FrontendCurrencies( $this, $this->utils );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

		if ( is_admin() ) {
			add_filter( 'woocommerce_get_settings_pages', [ $this, 'init_settings_pages' ] );
			add_action( 'admin_init', [ __CLASS__, 'add_woo_admin_notes' ] );
		}
	}

	/**
	 * Initialize the REST API controller.
	 *
	 * @return void
	 */
	public function init_rest_api() {
		$api_controller = new RestController( \WC_Payments::create_api_client() );
		$api_controller->register_routes();
	}

	/**
	 * Initialize the Widgets.
	 *
	 * @return void
	 */
	public function init_widgets() {
		register_widget( new CurrencySwitcherWidget( $this, $this->compatibility ) );
	}

	/**
	 * Initialize the Settings Pages.
	 *
	 * @param array $settings_pages The settings pages.
	 *
	 * @return array The new settings pages.
	 */
	public function init_settings_pages( $settings_pages ): array {
		$settings_pages[] = new Settings( $this );
		return $settings_pages;
	}

	/**
	 * Register the CSS and JS admin scripts.
	 *
	 * @return void
	 */
	public function register_admin_scripts() {
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
	 * Load the admin assets.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts() {
		global $current_tab, $current_section;

		$this->register_admin_scripts();

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
	 * @return ?array
	 */
	public function get_cached_currencies() {
		if ( ! $this->payments_api_client->is_server_connected() ) {
			return null;
		}

		$cache_data = $this->read_currencies_from_cache();

		// If the option contains the error value, return false early and do not attempt another API call.
		if ( isset( $cache_data['currencies'] ) && self::CURRENCY_RETRIEVAL_ERROR === $cache_data['currencies'] ) {
			return null;
		}

		// If an array of currencies was returned from the cache, return it here.
		if ( null !== $cache_data ) {
			return $cache_data;
		}

		// If the cache was expired or something went wrong, make a call to the server to get the
		// currency data.
		try {
			$currency_data = $this->payments_api_client->get_currency_rates( get_woocommerce_currency() );
		} catch ( API_Exception $e ) {
			// Failed to retrieve currencies from the server. Exception is logged in http client.
			// Rate limit for a short amount of time by caching the failure.
			$this->cache_currencies( self::CURRENCY_RETRIEVAL_ERROR, time(), 1 * MINUTE_IN_SECONDS );

			// Return null to signal currency retrieval error.
			return null;
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
	 * Returns the Compatibility instance.
	 *
	 * @return Compatibility
	 */
	public function get_compatibility() {
		return $this->compatibility;
	}

	/**
	 * Returns the FrontendPrices instance.
	 *
	 * @return FrontendPrices
	 */
	public function get_frontend_prices(): FrontendPrices {
		return $this->frontend_prices;
	}

	/**
	 * Returns the FrontendCurrencies instance.
	 *
	 * @return FrontendCurrencies
	 */
	public function get_frontend_currencies(): FrontendCurrencies {
		return $this->frontend_currencies;
	}

	/**
	 * Sets up the available currencies, which are alphabetical by name.
	 *
	 * @return void
	 */
	private function initialize_available_currencies() {
		// Add default store currency with a rate of 1.0.
		$woocommerce_currency                                = get_woocommerce_currency();
		$this->available_currencies[ $woocommerce_currency ] = new Currency( $woocommerce_currency, 1.0 );

		$available_currencies = [];

		$wc_currencies = get_woocommerce_currencies();
		$cache_data    = $this->get_cached_currencies();

		foreach ( $wc_currencies as $currency_code => $currency_name ) {
			$currency_rate = $cache_data['currencies'][ $currency_code ] ?? 1.0;
			$update_time   = $cache_data['updated'] ?? null;
			$new_currency  = new Currency( $currency_code, $currency_rate, $update_time );

			// Add this to our list of available currencies.
			$available_currencies[ $new_currency->get_name() ] = $new_currency;
		}

		ksort( $available_currencies );

		foreach ( $available_currencies as $currency ) {
			$this->available_currencies[ $currency->get_code() ] = $currency;
		}
	}

	/**
	 * Sets up the enabled currencies.
	 *
	 * @return void
	 */
	private function initialize_enabled_currencies() {
		$available_currencies     = $this->get_available_currencies();
		$enabled_currency_codes   = get_option( $this->id . '_enabled_currencies', [] );
		$default_code             = $this->get_default_currency()->get_code();
		$default                  = [];
		$enabled_currency_codes[] = $default_code;

		// This allows to keep the alphabetical sorting by name.
		$enabled_currencies = array_filter(
			$available_currencies,
			function( $currency ) use ( $enabled_currency_codes ) {
				return in_array( $currency->get_code(), $enabled_currency_codes, true );
			}
		);

		$this->enabled_currencies = [];

		foreach ( $enabled_currencies as $enabled_currency ) {
			// Get the charm and rounding for each enabled currency and add the currencies to the object property.
			$currency = clone $enabled_currency;
			$charm    = get_option( $this->id . '_price_charm_' . $currency->get_id(), 0.00 );
			$rounding = get_option( $this->id . '_price_rounding_' . $currency->get_id(), $currency->get_is_zero_decimal() ? '100' : '1.00' );
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
	 *
	 * @return void
	 */
	private function set_default_currency() {
		$this->default_currency = $this->available_currencies[ get_woocommerce_currency() ] ?? null;
	}

	/**
	 * Gets the currencies available.
	 *
	 * @return Currency[] Array of Currency objects.
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
	 * @return Currency[] Array of Currency objects.
	 */
	public function get_enabled_currencies(): array {
		return $this->enabled_currencies;
	}

	/**
	 * Sets the enabled currencies for the store.
	 *
	 * @param array $currencies Array of currency codes to be enabled.
	 *
	 * @return void
	 */
	public function set_enabled_currencies( $currencies = [] ) {
		if ( 0 < count( $currencies ) ) {
			update_option( $this->id . '_enabled_currencies', $currencies );
			$this->initialize_enabled_currencies();
		}
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

		$code = $this->compatibility->override_selected_currency() ? $this->compatibility->override_selected_currency() : $code;

		return $this->get_enabled_currencies()[ $code ] ?? $this->default_currency;
	}

	/**
	 * Update the selected currency from a currency code.
	 *
	 * @param string $currency_code Three letter currency code.
	 *
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

		// Recalculate cart when currency changes.
		if ( did_action( 'wp_loaded' ) ) {
			$this->recalculate_cart();
		} else {
			add_action( 'wp_loaded', [ $this, 'recalculate_cart' ] );
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
	 * Gets the configured value for apply charm pricing only to products.
	 *
	 * @return mixed The configured value.
	 */
	public function get_apply_charm_only_to_products() {
		return apply_filters( 'wcpay_multi_currency_apply_charm_only_to_products', true );
	}

	/**
	 * Gets the converted price using the current currency with the rounding and charm pricing settings.
	 *
	 * @param mixed  $price The price to be converted.
	 * @param string $type The type of price being converted. One of 'product', 'shipping', 'tax', 'coupon', or 'exchange_rate'.
	 *
	 * @return float The converted price.
	 */
	public function get_price( $price, string $type ): float {
		$supported_types = [ 'product', 'shipping', 'tax', 'coupon', 'exchange_rate' ];
		$currency        = $this->get_selected_currency();

		if ( ! in_array( $type, $supported_types, true ) || $currency->get_is_default() ) {
			return (float) $price;
		}

		$converted_price = ( (float) $price ) * $currency->get_rate();

		if ( 'tax' === $type || 'coupon' === $type || 'exchange_rate' === $type ) {
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
	 *
	 * @return void
	 */
	public function recalculate_cart() {
		WC()->cart->calculate_totals();
	}

	/**
	 * Adds Multi-Currency notes to the WC-Admin inbox.
	 *
	 * @return void
	 */
	public static function add_woo_admin_notes() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			NoteMultiCurrencyAvailable::possibly_add_note();
		}
	}

	/**
	 * Removes Multi-Currency notes from the WC-Admin inbox.
	 *
	 * @return void
	 */
	public static function remove_woo_admin_notes() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			NoteMultiCurrencyAvailable::possibly_delete_note();
		}
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
			$price = $this->ceil_price( $price, floatval( $currency->get_rounding() ) );
		}

		if ( $apply_charm_pricing ) {
			$price += floatval( $currency->get_charm() );
		}

		// Do not return negative prices (possible because of $currency->get_charm()).
		return max( 0, $price );
	}

	/**
	 * Ceils the price to the next number based on the rounding value.
	 *
	 * @param float $price    The price to be ceiled.
	 * @param float $rounding The rounding option.
	 *
	 * @return float The ceiled price.
	 */
	protected function ceil_price( float $price, float $rounding ): float {
		if ( 0.00 === $rounding ) {
			return $price;
		}
		return ceil( $price / $rounding ) * $rounding;
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
	private function cache_currencies( $currencies, int $updated = null, int $expiration = null ): bool {
		// Default $expiration to 6 hours if not set.
		if ( null === $expiration ) {
			$expiration = 6 * HOUR_IN_SECONDS;
		}

		// Default $updated to the current time.
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
	 * Checks to see if the store currency has changed. If it has, this will
	 * also update the option containing the store currency.
	 *
	 * @return bool
	 */
	private function check_store_currency_for_change(): bool {
		$last_known_currency  = get_option( $this->id . '_store_currency', false );
		$woocommerce_currency = get_woocommerce_currency();

		// If the last known currency was not set, update the option to set it and return false.
		if ( ! $last_known_currency ) {
			update_option( $this->id . '_store_currency', $woocommerce_currency );
			return false;
		}

		if ( $last_known_currency !== $woocommerce_currency ) {
			update_option( $this->id . '_store_currency', $woocommerce_currency );
			return true;
		}

		return false;
	}

	/**
	 * Called when the store currency has changed. Puts any manual rate currencies into an option for a notice to display.
	 *
	 * @return void
	 */
	private function update_manual_rate_currencies_notice_option() {
		$enabled_currencies = $this->get_enabled_currencies();
		$manual_currencies  = [];

		// Check enabled currencies for manual rates.
		foreach ( $enabled_currencies as $currency ) {
			$rate_type = get_option( $this->id . '_exchange_rate_' . $currency->get_id(), false );
			if ( 'manual' === $rate_type ) {
				$manual_currencies[] = $currency->get_name();
			}
		}

		if ( 0 < count( $manual_currencies ) ) {
			update_option( $this->id . '_show_store_currency_changed_notice', $manual_currencies );
		}
	}


	/**
	 * Read the currency data from the WP option we cache it in.
	 *
	 * @return ?array
	 */
	private function read_currencies_from_cache() {
		$currency_cache = get_option( self::CURRENCY_CACHE_OPTION );

		if ( false === $currency_cache || ! is_array( $currency_cache ) || ! isset( $currency_cache['currencies'] ) || ! isset( $currency_cache['expires'] ) || ! isset( $currency_cache['updated'] ) ) {
			// No option found or the data isn't in the format we expect.
			return null;
		}

		// Return false if the cache has expired, triggering another fetch.
		if ( $currency_cache['expires'] < time() ) {
			return null;
		}

		// We have fresh currency data in the cache, so return it.
		return $currency_cache;
	}
}
