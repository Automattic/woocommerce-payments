<?php
/**
 * Class MultiCurrency
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency;

use WC_Payments;
use WC_Payments_Account;
use WC_Payments_Utils;
use WC_Payments_API_Client;
use WC_Payments_Localization_Service;
use WCPay\Constants\Country_Code;
use WCPay\Constants\Currency_Code;
use WCPay\Exceptions\API_Exception;
use WCPay\Database_Cache;
use WCPay\Logger;
use WCPay\MultiCurrency\Exceptions\InvalidCurrencyException;
use WCPay\MultiCurrency\Exceptions\InvalidCurrencyRateException;
use WCPay\MultiCurrency\Helpers\OrderMetaHelper;
use WCPay\MultiCurrency\Notes\NoteMultiCurrencyAvailable;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi-Currency functionality.
 */
class MultiCurrency {

	const CURRENCY_SESSION_KEY    = 'wcpay_currency';
	const CURRENCY_META_KEY       = 'wcpay_currency';
	const FILTER_PREFIX           = 'wcpay_multi_currency_';
	const CUSTOMER_CURRENCIES_KEY = 'wcpay_multi_currency_stored_customer_currencies';

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
	 * Static flag to show if the currencies initialization has been completed
	 *
	 * @var bool
	 */
	protected static $is_initialized = false;

	/**
	 * Compatibility instance.
	 *
	 * @var Compatibility
	 */
	protected $compatibility;

	/**
	 * Geolocation instance.
	 *
	 * @var Geolocation
	 */
	protected $geolocation;

	/**
	 * The Currency Switcher Widget instance.
	 *
	 * @var null|CurrencySwitcherWidget
	 */
	protected $currency_switcher_widget;

	/**
	 * Gutenberg Block implementation of the Currency Switcher Widget instance.
	 *
	 * @var CurrencySwitcherBlock
	 */
	protected $currency_switcher_block;

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
	 * BackendCurrencies instance.
	 *
	 * @var BackendCurrencies
	 */
	protected $backend_currencies;

	/**
	 * StorefrontIntegration instance.
	 *
	 * @var StorefrontIntegration
	 */
	protected $storefront_integration;

	/**
	 * The available currencies.
	 *
	 * @var Currency[]|null
	 */
	protected $available_currencies;

	/**
	 * The default currency.
	 *
	 * @var Currency|null
	 */
	protected $default_currency;

	/**
	 * The enabled currencies.
	 *
	 * @var Currency[]|null
	 */
	protected $enabled_currencies;

	/**
	 * Client for making requests to the WooCommerce Payments API
	 *
	 * @var WC_Payments_API_Client
	 */
	private $payments_api_client;

	/**
	 * Instance of WC_Payments_Account.
	 *
	 * @var WC_Payments_Account
	 */
	private $payments_account;

	/**
	 * Instance of WC_Payments_Localization_Service.
	 *
	 * @var WC_Payments_Localization_Service
	 */
	private $localization_service;

	/**
	 * Instance of Database_Cache.
	 *
	 * @var Database_Cache
	 */
	private $database_cache;

	/**
	 * Tracking instance.
	 *
	 * @var Tracking
	 */
	protected $tracking;

	/**
	 * Simulation variables array.
	 *
	 * @var array
	 */
	protected $simulation_params = [];

	/**
	 * Instance of OrderMetaHelper.
	 *
	 * @var OrderMetaHelper
	 */
	private $order_meta_helper;

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
			self::$instance = new self( WC_Payments::get_payments_api_client(), WC_Payments::get_account_service(), WC_Payments::get_localization_service(), WC_Payments::get_database_cache() );
			self::$instance->init_hooks();
		}
		return self::$instance;
	}

	/**
	 * Class constructor.
	 *
	 * @param WC_Payments_API_Client           $payments_api_client  Payments API client.
	 * @param WC_Payments_Account              $payments_account     Payments Account instance.
	 * @param WC_Payments_Localization_Service $localization_service Localization Service instance.
	 * @param Database_Cache                   $database_cache       Database Cache instance.
	 * @param Utils|null                       $utils                Optional Utils instance.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Account $payments_account, WC_Payments_Localization_Service $localization_service, Database_Cache $database_cache, Utils $utils = null ) {
		$this->payments_api_client  = $payments_api_client;
		$this->payments_account     = $payments_account;
		$this->localization_service = $localization_service;
		$this->database_cache       = $database_cache;
		// If a Utils instance is not passed as argument, initialize it. This allows to mock it in tests.
		$this->utils                   = $utils ?? new Utils();
		$this->geolocation             = new Geolocation( $this->localization_service );
		$this->compatibility           = new Compatibility( $this, $this->utils );
		$this->currency_switcher_block = new CurrencySwitcherBlock( $this, $this->compatibility );
	}

	/**
	 * Initializes this class' WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( is_admin() && current_user_can( 'manage_woocommerce' ) ) {
			add_filter( 'woocommerce_get_settings_pages', [ $this, 'init_settings_pages' ] );
			// Enqueue the scripts after the main WC_Payments_Admin does.
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ], 20 );
			add_action( 'admin_head', [ $this, 'set_client_format_and_rounding_precision' ] );
		}

		add_action( 'init', [ $this, 'init' ] );
		add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
		add_action( 'widgets_init', [ $this, 'init_widgets' ] );

		$is_frontend_request = ! is_admin() && ! defined( 'DOING_CRON' ) && ! WC()->is_rest_api_request();

		if ( $is_frontend_request ) {
			// Make sure that this runs after the main init function.
			add_action( 'init', [ $this, 'update_selected_currency_by_url' ], 11 );
			add_action( 'init', [ $this, 'update_selected_currency_by_geolocation' ], 12 );
			add_action( 'init', [ $this, 'possible_simulation_activation' ], 13 );
			add_action( 'woocommerce_created_customer', [ $this, 'set_new_customer_currency_meta' ] );
		}

		$this->currency_switcher_block->init_hooks();
	}

	/**
	 * Called after the WooCommerce session has been initialized. Initialises the available currencies,
	 * default currency and enabled currencies for the Multi-Currency plugin.
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

		$payment_method_compat = new PaymentMethodsCompatibility( $this, WC_Payments::get_gateway() );
		$admin_notices         = new AdminNotices();
		$user_settings         = new UserSettings( $this );
		new Analytics( $this );

		$this->frontend_prices     = new FrontendPrices( $this, $this->compatibility );
		$this->frontend_currencies = new FrontendCurrencies( $this, $this->localization_service, $this->utils, $this->compatibility );
		$this->backend_currencies  = new BackendCurrencies( $this, $this->localization_service );
		$this->tracking            = new Tracking( $this );
		$this->order_meta_helper   = new OrderMetaHelper( $this->payments_api_client );

		// Init all of the hooks.
		$payment_method_compat->init_hooks();
		$admin_notices->init_hooks();
		$user_settings->init_hooks();
		$this->frontend_prices->init_hooks();
		$this->frontend_currencies->init_hooks();
		$this->backend_currencies->init_hooks();
		$this->tracking->init_hooks();
		$this->order_meta_helper->init_hooks();

		add_action( 'woocommerce_order_refunded', [ $this, 'add_order_meta_on_refund' ], 50, 2 );

		// Check to make sure there are enabled currencies, then for Storefront being active, and then load the integration.
		$theme = wp_get_theme();
		if ( 'storefront' === $theme->get_stylesheet() || 'storefront' === $theme->get_template() ) {
			$this->storefront_integration = new StorefrontIntegration( $this );
		}

		if ( is_admin() ) {
			add_action( 'admin_init', [ __CLASS__, 'add_woo_admin_notes' ] );
		}

		// Update the customer currencies option after an order status change.
		add_action( 'woocommerce_order_status_changed', [ $this, 'maybe_update_customer_currencies_option' ] );

		$this->maybe_add_cache_cookie();

		static::$is_initialized = true;
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
	 * Initialize the legacy widgets.
	 *
	 * @return void
	 */
	public function init_widgets() {
		// Register the legacy widget.
		$this->currency_switcher_widget = new CurrencySwitcherWidget( $this, $this->compatibility );
		register_widget( $this->currency_switcher_widget );
	}

	/**
	 * Initialize the Settings Pages.
	 *
	 * @param array $settings_pages The settings pages.
	 *
	 * @return array The new settings pages.
	 */
	public function init_settings_pages( $settings_pages ): array {
		// We don't need to check if Stripe is connected for the
		// Settings page generation on the incoming CLI and async job calls.
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'WPCOM_JOBS' ) && WPCOM_JOBS ) ) {
			return $settings_pages;
		}

		if ( $this->payments_account->is_stripe_connected() ) {
			$settings = new Settings( $this );
			$settings->init_hooks();

			$settings_pages[] = $settings;
		} else {
			$settings_onboard_cta = new SettingsOnboardCta( $this );
			$settings_onboard_cta->init_hooks();

			$settings_pages[] = $settings_onboard_cta;
		}

		return $settings_pages;
	}

	/**
	 * Load the admin assets.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts() {
		global $current_tab;

		// Enqueue the settings JS and CSS only on the WCPay multi-currency settings page.
		if ( 'wcpay_multi_currency' !== $current_tab ) {
			return;
		}

		$this->register_admin_scripts();

		wp_enqueue_script( 'WCPAY_MULTI_CURRENCY_SETTINGS' );
		WC_Payments_Utils::enqueue_style( 'WCPAY_MULTI_CURRENCY_SETTINGS' );
	}

	/**
	 * Wipes the cached currency data option, forcing to re-fetch the data from WPCOM.
	 *
	 * @return void
	 */
	public function clear_cache() {
		Logger::debug( 'Clearing the cache to force new rates to be fetched from the server.' );
		$this->database_cache->delete( Database_Cache::CURRENCIES_KEY );
	}

	/**
	 * Gets and caches the data for the currency rates from the server.
	 * Will be returned as an array with three keys, 'currencies' (the currencies), 'expires' (the expiry time)
	 * and 'updated' (when this data was fetched from the API).
	 *
	 * @return ?array
	 */
	public function get_cached_currencies() {
		$cached_data = $this->database_cache->get( Database_Cache::CURRENCIES_KEY );
		// If connection to server cannot be established, or if Stripe is not connected, or if the account is rejected, return expired data or null.
		if ( ! $this->payments_api_client->is_server_connected() || ! $this->payments_account->is_stripe_connected() || $this->payments_account->is_account_rejected() ) {
			return $cached_data ?? null;
		}

		return $this->database_cache->get_or_add(
			Database_Cache::CURRENCIES_KEY,
			function () {
				try {
					$currency_data = $this->payments_api_client->get_currency_rates( strtolower( get_woocommerce_currency() ) );
					return [
						'currencies' => $currency_data,
						'updated'    => time(),
					];
				} catch ( API_Exception $e ) {
					return null;
				}
			},
			function ( $data ) {
				return is_array( $data ) && isset( $data['currencies'], $data['updated'] );
			}
		);
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
	 * Returns the Currency Switcher Widget instance.
	 *
	 * @return CurrencySwitcherWidget|null
	 */
	public function get_currency_switcher_widget() {
		return $this->currency_switcher_widget;
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
	 * Returns the StorefrontIntegration instance.
	 *
	 * @return StorefrontIntegration|null
	 */
	public function get_storefront_integration() {
		return $this->storefront_integration;
	}

	/**
	 * Generates the switcher widget markup.
	 *
	 * @param array $instance The widget's instance settings.
	 * @param array $args     The widget's arguments.
	 *
	 * @return string The widget markup.
	 */
	public function get_switcher_widget_markup( array $instance = [], array $args = [] ): string {
		/**
		 * The spl_object_hash function is used here due to we register the widget with an instance of the widget and
		 * not the class name of the widget. WordPress core takes the instance and passes it through spl_object_hash
		 * to get a hash and adds that as the widget's name in the $wp_widget_factory->widgets[] array. In order to
		 * call the_widget, you need to have the name of the widget, so we get the instance and hash to use.
		 */
		ob_start();
		the_widget(
			spl_object_hash( $this->get_currency_switcher_widget() ),
			apply_filters( self::FILTER_PREFIX . 'theme_widget_instance', $instance ),
			apply_filters( self::FILTER_PREFIX . 'theme_widget_args', $args )
		);
		return ob_get_clean();
	}

	/**
	 * Returns the store's current available, enabled, and default currencies.
	 *
	 * @return array
	 */
	public function get_store_currencies(): array {
		return [
			'available' => $this->get_available_currencies(),
			'enabled'   => $this->get_enabled_currencies(),
			'default'   => $this->get_default_currency(),
		];
	}

	/**
	 * Gets the currency settings for a single currency.
	 *
	 * @param   string $currency_code The currency code to get settings for.
	 *
	 * @return  array The currency's settings.
	 *
	 * @throws InvalidCurrencyException
	 */
	public function get_single_currency_settings( string $currency_code ): array {
		// Confirm the currency code is valid before trying to get the settings.
		if ( ! array_key_exists( strtoupper( $currency_code ), $this->get_available_currencies() ) ) {
			$this->log_and_throw_invalid_currency_exception( __FUNCTION__, $currency_code );
		}

		$currency_code = strtolower( $currency_code );
		return [
			'exchange_rate_type' => get_option( 'wcpay_multi_currency_exchange_rate_' . $currency_code, 'automatic' ),
			'manual_rate'        => get_option( 'wcpay_multi_currency_manual_rate_' . $currency_code, null ),
			'price_rounding'     => get_option( 'wcpay_multi_currency_price_rounding_' . $currency_code, null ),
			'price_charm'        => get_option( 'wcpay_multi_currency_price_charm_' . $currency_code, null ),
		];
	}

	/**
	 * Updates the currency settings for a single currency.
	 *
	 * @param string $currency_code      The single currency code to be updated.
	 * @param string $exchange_rate_type The exchange rate type setting.
	 * @param float  $price_rounding     The price rounding setting.
	 * @param float  $price_charm        The price charm setting.
	 * @param ?float $manual_rate        The manual rate setting, or null.
	 *
	 * @return void
	 *
	 * @throws InvalidCurrencyException
	 * @throws InvalidCurrencyRateException
	 */
	public function update_single_currency_settings( string $currency_code, string $exchange_rate_type, float $price_rounding, float $price_charm, $manual_rate = null ) {
		// Confirm the currency code is valid before trying to update the settings.
		if ( ! array_key_exists( strtoupper( $currency_code ), $this->get_available_currencies() ) ) {
			$this->log_and_throw_invalid_currency_exception( __FUNCTION__, $currency_code );
		}

		$currency_code = strtolower( $currency_code );

		if ( 'manual' === $exchange_rate_type && ! is_null( $manual_rate ) ) {
			if ( ! is_numeric( $manual_rate ) || 0 >= $manual_rate ) {
				$message = 'Invalid manual currency rate passed to update_single_currency_settings: ' . $manual_rate;
				Logger::error( $message );
				throw new InvalidCurrencyRateException( esc_html( $message ), 'wcpay_multi_currency_invalid_currency_rate', 500 );
			}
			update_option( 'wcpay_multi_currency_manual_rate_' . $currency_code, $manual_rate );
		}

		update_option( 'wcpay_multi_currency_price_rounding_' . $currency_code, $price_rounding );
		update_option( 'wcpay_multi_currency_price_charm_' . $currency_code, $price_charm );
		if ( in_array( $exchange_rate_type, [ 'automatic', 'manual' ], true ) ) {
			update_option( 'wcpay_multi_currency_exchange_rate_' . $currency_code, esc_attr( $exchange_rate_type ) );
		}
	}

	/**
	 * Updates the customer currencies option.
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return void
	 */
	public function maybe_update_customer_currencies_option( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$currency   = strtoupper( $order->get_currency() );
		$currencies = self::get_all_customer_currencies();

		// Skip if the currency is already in the list.
		if ( in_array( $currency, $currencies, true ) ) {
			return;
		}

		$currencies[] = $currency;
		update_option( self::CUSTOMER_CURRENCIES_KEY, $currencies );
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

		$currencies = $this->get_account_available_currencies();
		$cache_data = $this->get_cached_currencies();

		foreach ( $currencies as $currency_code ) {
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
		$enabled_currency_codes   = is_array( $enabled_currency_codes ) ? $enabled_currency_codes : [];
		$default_code             = $this->get_default_currency()->get_code();
		$default                  = [];
		$enabled_currency_codes[] = $default_code;

		// This allows to keep the alphabetical sorting by name.
		$enabled_currencies = array_filter(
			$available_currencies,
			function ( $currency ) use ( $enabled_currency_codes ) {
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
		$available_currencies   = $this->get_available_currencies();
		$this->default_currency = $available_currencies[ get_woocommerce_currency() ] ?? null;
	}

	/**
	 * Gets the currencies available. Initializes it if needed.
	 *
	 * @return Currency[] Array of Currency objects.
	 */
	public function get_available_currencies(): array {
		if ( null === $this->available_currencies ) {
			$this->init();
		}

		return $this->available_currencies ?? [];
	}

	/**
	 * Gets the store base currency. Initializes it if needed.
	 *
	 * @return Currency The store base currency.
	 */
	public function get_default_currency(): Currency {
		if ( null === $this->default_currency ) {
			$this->init();
		}

		return $this->default_currency ?? new Currency( get_woocommerce_currency() );
	}

	/**
	 * Gets the currently enabled currencies. Initializes it if needed.
	 *
	 * @return Currency[] Array of Currency objects.
	 */
	public function get_enabled_currencies(): array {
		if ( null === $this->enabled_currencies ) {
			$this->init();
		}

		return $this->enabled_currencies ?? [];
	}

	/**
	 * Sets the enabled currencies for the store.
	 *
	 * @param string[] $currencies Array of currency codes to be enabled.
	 *
	 * @return void
	 *
	 * @throws InvalidCurrencyException
	 */
	public function set_enabled_currencies( $currencies = [] ) {
		// If curriencies is not an array, or if there are no currencies, just exit.
		if ( ! is_array( $currencies ) || 0 === count( $currencies ) ) {
			return;
		}

		// Confirm the currencies submitted are available/valid currencies.
		$invalid_currencies = array_diff( $currencies, array_keys( $this->get_available_currencies() ) );
		if ( 0 < count( $invalid_currencies ) ) {
			$this->log_and_throw_invalid_currency_exception( __FUNCTION__, implode( ', ', $invalid_currencies ) );
		}

		// Get the currencies that were removed before they are updated.
		$removed_currencies = array_diff( array_keys( $this->get_enabled_currencies() ), $currencies );

		// Update the enabled currencies and reinitialize.
		update_option( $this->id . '_enabled_currencies', $currencies );
		$this->initialize_enabled_currencies();

		Logger::debug(
			'Enabled currencies updated: '
			. var_export( $currencies, true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		);

		// Now remove the removed currencies settings.
		if ( 0 < count( $removed_currencies ) ) {
			$this->remove_currencies_settings( $removed_currencies );
		}
	}

	/**
	 * Gets the user selected currency, or `$default_currency` if is not set.
	 *
	 * @return Currency
	 */
	public function get_selected_currency(): Currency {
		$multi_currency_code = $this->compatibility->override_selected_currency();
		$currency_code       = $multi_currency_code ? $multi_currency_code : $this->get_stored_currency_code();

		return $this->get_enabled_currencies()[ $currency_code ] ?? $this->get_default_currency();
	}

	/**
	 * Update the selected currency from a currency code.
	 *
	 * @param string $currency_code Three letter currency code.
	 * @param bool   $persist_change Set true to store the change in the session cookie if it doesn't exist yet.
	 *
	 * @return void
	 */
	public function update_selected_currency( string $currency_code, bool $persist_change = true ) {
		$code     = strtoupper( $currency_code );
		$user_id  = get_current_user_id();
		$currency = $this->get_enabled_currencies()[ $code ] ?? null;

		// We discard the cache for the front-end.
		$this->frontend_currencies->selected_currency_changed();

		if ( null === $currency ) {
			return;
		}

		if ( 0 === $user_id && WC()->session ) {
			WC()->session->set( self::CURRENCY_SESSION_KEY, $currency->get_code() );
			// Set the session cookie if is not yet to persist the selected currency.
			if ( ! WC()->session->has_session() && ! headers_sent() && $persist_change ) {
				$this->utils->set_customer_session_cookie( true );
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

		$this->maybe_add_cache_cookie();
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
	 * Update the selected currency from the user's geolocation country.
	 *
	 * @return void
	 */
	public function update_selected_currency_by_geolocation() {
		// We only want to automatically set the currency if the option is enabled and it shouldn't be disabled for any reason.
		if ( ! $this->is_using_auto_currency_switching() || $this->compatibility->should_disable_currency_switching() ) {
			return;
		}

		// Display notice, prevent duplicates in simulation.
		if ( ! has_action( 'wp_footer', [ $this, 'display_geolocation_currency_update_notice' ] ) ) {
			add_action( 'wp_footer', [ $this, 'display_geolocation_currency_update_notice' ] );
		}

		// Update currency only if it's already not set.
		if ( $this->get_stored_currency_code() ) {
			return;
		}

		$currency = $this->geolocation->get_currency_by_customer_location();

		if ( empty( $this->get_enabled_currencies()[ $currency ] ) ) {
			return;
		}

		$this->update_selected_currency( $currency, false );
	}

	/**
	 * Gets the configured value for apply charm pricing only to products.
	 *
	 * @return mixed The configured value.
	 */
	public function get_apply_charm_only_to_products() {
		return apply_filters( self::FILTER_PREFIX . 'apply_charm_only_to_products', true );
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
	 * Gets a raw converted amount based on the amount and currency codes passed.
	 * This is a helper method for external conversions, if needed.
	 *
	 * @param float  $amount        The amount to be converted.
	 * @param string $to_currency   The 3 letter currency code to convert the amount to.
	 * @param string $from_currency The 3 letter currency code to convert the amount from.
	 *
	 * @return float The converted amount.
	 *
	 * @throws InvalidCurrencyException
	 * @throws InvalidCurrencyRateException
	 */
	public function get_raw_conversion( float $amount, string $to_currency, string $from_currency = '' ): float {
		$enabled_currencies = $this->get_enabled_currencies();

		// If the from_currency is not set, use the store currency.
		if ( '' === $from_currency ) {
			$from_currency = $this->get_default_currency()->get_code();
		}

		// We throw an exception if either of the currencies are not enabled.
		$to_currency   = strtoupper( $to_currency );
		$from_currency = strtoupper( $from_currency );
		foreach ( [ $to_currency, $from_currency ] as $code ) {
			if ( ! isset( $enabled_currencies[ $code ] ) ) {
				$this->log_and_throw_invalid_currency_exception( __FUNCTION__, $code );
			}
		}

		// Get the rates.
		$to_currency_rate   = $enabled_currencies[ $to_currency ]->get_rate();
		$from_currency_rate = $enabled_currencies[ $from_currency ]->get_rate();

		// Throw an exception in case from_currency_rate is less than or equal to 0.
		if ( 0 >= $from_currency_rate ) {
			$message = 'Invalid rate for from_currency in get_raw_conversion: ' . $from_currency_rate;
			Logger::error( $message );
			throw new InvalidCurrencyRateException( esc_html( $message ), 'wcpay_multi_currency_invalid_currency_rate', 500 );
		}

		$amount = $amount * ( $to_currency_rate / $from_currency_rate );

		return (float) $amount;
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
	 * When an order is refunded, a new psuedo order is created to represent the refund.
	 * We want to check if the original order was a multi-currency order, and if so, copy the meta data
	 * to the new order.
	 *
	 * @param int $order_id The order ID.
	 * @param int $refund_id The refund order ID.
	 */
	public function add_order_meta_on_refund( $order_id, $refund_id ) {
		$default_currency = $this->get_default_currency();

		$order  = wc_get_order( $order_id );
		$refund = wc_get_order( $refund_id );

		// Do not add exchange rate if order was made in the store's default currency.
		if ( ! $order || ! $refund || $default_currency->get_code() === $order->get_currency() ) {
			return;
		}

		$order_exchange_rate    = $order->get_meta( '_wcpay_multi_currency_order_exchange_rate', true );
		$stripe_exchange_rate   = $order->get_meta( '_wcpay_multi_currency_stripe_exchange_rate', true );
		$order_default_currency = $order->get_meta( '_wcpay_multi_currency_order_default_currency', true );

		$refund->update_meta_data( '_wcpay_multi_currency_order_exchange_rate', $order_exchange_rate );
		$refund->update_meta_data( '_wcpay_multi_currency_order_default_currency', $order_default_currency );
		if ( $stripe_exchange_rate ) {
			$refund->update_meta_data( '_wcpay_multi_currency_stripe_exchange_rate', $stripe_exchange_rate );
		}

		$refund->save_meta_data();
	}

	/**
	 * Displays a notice on the frontend informing the customer of the
	 * automatic currency switch.
	 */
	public function display_geolocation_currency_update_notice() {
		$current_currency    = $this->get_selected_currency();
		$store_currency      = get_option( 'woocommerce_currency' );
		$country             = $this->geolocation->get_country_by_customer_location();
		$geolocated_currency = $this->geolocation->get_currency_by_customer_location();
		$currencies          = get_woocommerce_currencies();

		// Don't run next checks if simulation is enabled.
		if ( ! $this->is_simulation_enabled() ) {
			// Do not display notice if using the store's default currency.
			if ( $store_currency === $current_currency->get_code() ) {
				return;
			}

			// Do not display notice for other currencies than geolocated.
			if ( $current_currency->get_code() !== $geolocated_currency ) {
				return;
			}
		}

		$message = sprintf(
		/* translators: %1 User's country, %2 Selected currency name, %3 Default store currency name */
			__( 'We noticed you\'re visiting from %1$s. We\'ve updated our prices to %2$s for your shopping convenience. <a>Use %3$s instead.</a>', 'woocommerce-payments' ),
			apply_filters( self::FILTER_PREFIX . 'override_notice_country', WC()->countries->countries[ $country ] ),
			apply_filters( self::FILTER_PREFIX . 'override_notice_currency_name', $current_currency->get_name() ),
			$currencies[ $store_currency ]
		);

		$notice_id = md5( $message );

		echo '<p class="woocommerce-store-notice demo_store" data-notice-id="' . esc_attr( $notice_id . 2 ) . '" style="display:none;">';
		// No need to escape here as the function called handles it.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo \WC_Payments_Utils::esc_interpolated_html(
			$message,
			[
				'a' => '<a href="?currency=' . esc_attr( $store_currency ) . '">',
			]
		);
		echo ' <a href="#" class="woocommerce-store-notice__dismiss-link">' . esc_html__( 'Dismiss', 'woocommerce-payments' ) . '</a></p>';
	}

	/**
	 * Sets a new customer's currency meta to what's in their session.
	 * This is needed for when a new user/customer is created during the checkout process.
	 *
	 * @param int $customer_id The user/customer id.
	 *
	 * @return void
	 */
	public function set_new_customer_currency_meta( $customer_id ) {
		$code = 0 !== $customer_id && WC()->session ? WC()->session->get( self::CURRENCY_SESSION_KEY ) : false;
		if ( $code ) {
			update_user_meta( $customer_id, self::CURRENCY_META_KEY, $code );
		}
	}

	/**
	 * Adds Multi-Currency notes to the WC-Admin inbox.
	 *
	 * @return void
	 */
	public static function add_woo_admin_notes() {
		// Do not try to add notes on ajax requests to improve their performance.
		if ( wp_doing_ajax() ) {
			return;
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			NoteMultiCurrencyAvailable::set_account( WC_Payments::get_account_service() );
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
		$price = $this->ceil_price( $price, (float) $currency->get_rounding() );

		if ( $apply_charm_pricing ) {
			$price += (float) $currency->get_charm();
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
	 * Returns the currency code stored for the user or in the session.
	 *
	 * @return string|null Currency code.
	 */
	private function get_stored_currency_code() {
		$user_id = get_current_user_id();

		if ( $user_id ) {
			return get_user_meta( $user_id, self::CURRENCY_META_KEY, true );
		}

		WC()->initialize_session();
		$currency_code = WC()->session->get( self::CURRENCY_SESSION_KEY );

		return is_string( $currency_code ) ? $currency_code : null;
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
	 * Accepts an array of currencies that should have their settings removed.
	 *
	 * @param array $currencies Array of Currency objects or 3 letter currency codes.
	 *
	 * @return void
	 */
	private function remove_currencies_settings( array $currencies ) {

		foreach ( $currencies as $currency ) {
			$this->remove_currency_settings( $currency );
		}
	}

	/**
	 * Will remove a currency's settings if it is not enabled.
	 *
	 * @param mixed $currency Currency object or 3 letter currency code.
	 *
	 * @return void
	 */
	private function remove_currency_settings( $currency ) {
		$code = is_a( $currency, Currency::class ) ? $currency->get_code() : strtoupper( $currency );

		// Bail if the currency code passed is not 3 characters, or if the currency is presently enabled.
		if ( 3 !== strlen( $code ) || isset( $this->get_enabled_currencies()[ $code ] ) ) {
			return;
		}

		$settings = [
			'price_charm',
			'price_rounding',
			'manual_rate',
			'exchange_rate',
		];

		// Go through each setting and remove them.
		foreach ( $settings as $setting ) {
			delete_option( $this->id . '_' . $setting . '_' . strtolower( $code ) );
		}
	}

	/**
	 * Returns the currencies enabled for the Stripe account that are
	 * also available in WC.
	 *
	 * Can be filtered with the 'wcpay_multi_currency_available_currencies' hook.
	 *
	 * @return array Array with the available currencies' codes.
	 */
	private function get_account_available_currencies(): array {
		// If Stripe is not connected, return an empty array. This prevents using MC without being connected to Stripe.
		if ( ! $this->payments_account->is_stripe_connected() ) {
			return [];
		}

		$wc_currencies      = array_keys( get_woocommerce_currencies() );
		$account_currencies = $wc_currencies;

		$account              = $this->payments_account->get_cached_account_data();
		$supported_currencies = $this->payments_account->get_account_customer_supported_currencies();
		if ( $account && ! empty( $supported_currencies ) ) {
			$account_currencies = array_map( 'strtoupper', $supported_currencies );
		}

		/**
		 * Filter the available currencies for WooCommerce Multi-Currency.
		 *
		 * This filter can be used to modify the currencies available for WC Pay
		 * Multi-Currency. Currencies have to be added in uppercase and should
		 * also be available in `get_woocommerce_currencies` for them to work.
		 *
		 * @since 2.8.0
		 *
		 * @param array $available_currencies Current available currencies. Calculated based on
		 *                                    WC Pay's account currencies and WC currencies.
		 */
		return apply_filters( self::FILTER_PREFIX . 'available_currencies', array_intersect( $account_currencies, $wc_currencies ) );
	}

	/**
	 * Checks if the merchant has enabled automatic currency switching and geolocation.
	 *
	 * @return bool
	 */
	public function is_using_auto_currency_switching(): bool {
		return 'yes' === get_option( $this->id . '_enable_auto_currency', 'no' );
	}

	/**
	 * Checks if the merchant has enabled the currency switcher widget.
	 *
	 * @return  bool
	 */
	public function is_using_storefront_switcher(): bool {
		return 'yes' === get_option( $this->id . '_enable_storefront_switcher', 'no' );
	}

	/**
	 * Gets the store settings.
	 *
	 * @return  array  The store settings.
	 */
	public function get_settings() {
		return [
			$this->id . '_enable_auto_currency'       => $this->is_using_auto_currency_switching(),
			$this->id . '_enable_storefront_switcher' => $this->is_using_storefront_switcher(),
			'site_theme'                              => wp_get_theme()->get( 'Name' ),
			'date_format'                             => esc_attr( get_option( 'date_format', 'F j, Y' ) ),
			'time_format'                             => esc_attr( get_option( 'time_format', 'g:i a' ) ),
			'store_url'                               => esc_attr( get_page_uri( wc_get_page_id( 'shop' ) ) ),
		];
	}

	/**
	 * Updates the store settings
	 *
	 * @param   array $params  Update requested values.
	 *
	 * @return  void
	 */
	public function update_settings( $params ) {
		$updateable_options = [
			'wcpay_multi_currency_enable_auto_currency',
			'wcpay_multi_currency_enable_storefront_switcher',
		];

		foreach ( $updateable_options as $key ) {
			if ( isset( $params[ $key ] ) ) {
				update_option( $key, sanitize_text_field( $params[ $key ] ) );
			}
		}
	}

	/**
	 * Apply client order currency format and reduces the rounding precision to 2.
	 *
	 * @return  void
	 */
	public function set_client_format_and_rounding_precision() {
		$screen = get_current_screen();
		if ( in_array( $screen->id, [ 'shop_order', 'woocommerce_page_wc-orders' ], true ) ) :
			$order = wc_get_order();
			if ( ! $order ) {
				return;
			}
			$currency                     = $order->get_currency();
			$currency_format_num_decimals = $this->backend_currencies->get_price_decimals( $currency );
			$currency_format_decimal_sep  = $this->backend_currencies->get_price_decimal_separator( $currency );
			$currency_format_thousand_sep = $this->backend_currencies->get_price_thousand_separator( $currency );
			$currency_format              = str_replace( [ '%1$s', '%2$s', '&nbsp;' ], [ '%s', '%v', ' ' ], $this->backend_currencies->get_woocommerce_price_format( $currency ) );

			$rounding_precision = wc_get_price_decimals() ?? wc_get_rounding_precision();
			?>
		<script>
		woocommerce_admin_meta_boxes.currency_format_num_decimals = <?php echo (int) $currency_format_num_decimals; ?>;
		woocommerce_admin_meta_boxes.currency_format_decimal_sep = '<?php echo esc_attr( $currency_format_decimal_sep ); ?>';
		woocommerce_admin_meta_boxes.currency_format_thousand_sep = '<?php echo esc_attr( $currency_format_thousand_sep ); ?>';
		woocommerce_admin_meta_boxes.currency_format = '<?php echo esc_attr( $currency_format ); ?>';
		woocommerce_admin_meta_boxes.rounding_precision = <?php echo (int) $rounding_precision; ?>;
		</script>
			<?php
		endif;
	}

	/**
	 * Register the CSS and JS admin scripts.
	 *
	 * @return void
	 */
	private function register_admin_scripts() {
		WC_Payments::register_script_with_dependencies( 'WCPAY_MULTI_CURRENCY_SETTINGS', 'dist/multi-currency', [ 'WCPAY_ADMIN_SETTINGS' ] );

		WC_Payments_Utils::register_style(
			'WCPAY_MULTI_CURRENCY_SETTINGS',
			plugins_url( 'dist/multi-currency.css', WCPAY_PLUGIN_FILE ),
			[ 'wc-components', 'WCPAY_ADMIN_SETTINGS' ],
			\WC_Payments::get_file_version( 'dist/multi-currency.css' ),
			'all'
		);
	}

	/**
	 * Validates the given currency code.
	 *
	 * @param   string $currency_code  The currency code to check validity.
	 *
	 * @return  string|false  Returns back the currency code in uppercase letters if it's valid, or `false` if not.
	 */
	public function validate_currency_code( $currency_code ) {
		return array_key_exists( strtoupper( $currency_code ), $this->available_currencies )
		? strtoupper( $currency_code )
		: false;
	}

	/**
	 * Get simulation params from querystring and activate when needed
	 *
	 * @return  void
	 */
	public function possible_simulation_activation() {
		// This is required in the MC onboarding simulation iframe.
		$this->simulation_params = $this->get_multi_currency_onboarding_simulation_variables();
		if ( ! $this->is_simulation_enabled() ) {
			return;
		}
		// Modify the page links to deliver required params in the simulation.
		$this->add_simulation_params_to_preview_urls();
		$this->simulate_client_currency();
	}

	/**
	 * Enables simulation of client browser currency.
	 *
	 * @return  void
	 */
	private function simulate_client_currency() {
		if ( ! $this->simulation_params['enable_auto_currency'] ) {
			return;
		}

		$countries = WC_Payments_Utils::supported_countries();

		$predefined_simulation_currencies = [
			Currency_Code::UNITED_STATES_DOLLAR => $countries[ Country_Code::UNITED_STATES ],
			Currency_Code::POUND_STERLING       => $countries[ Country_Code::UNITED_KINGDOM ],
		];

		$simulation_currency      = Currency_Code::UNITED_STATES_DOLLAR === get_option( 'woocommerce_currency', Currency_Code::UNITED_STATES_DOLLAR ) ? Currency_Code::POUND_STERLING : Currency_Code::UNITED_STATES_DOLLAR;
		$simulation_currency_name = $this->available_currencies[ $simulation_currency ]->get_name();
		$simulation_country       = $predefined_simulation_currencies[ $simulation_currency ];

		// Simulate client currency from geolocation.
		add_filter(
			'wcpay_multi_currency_override_notice_currency_name',
			function ( $selected_currency_name ) use ( $simulation_currency_name ) {
				return $simulation_currency_name;
			}
		);

		// Simulate client country from geolocation.
		add_filter(
			'wcpay_multi_currency_override_notice_country',
			function ( $selected_country ) use ( $simulation_country ) {
				return $simulation_country;
			}
		);

		// Always display the notice on simulation screen, prevent duplicate hooks.
		if ( ! has_action( 'wp_footer', [ $this, 'display_geolocation_currency_update_notice' ] ) ) {
			add_action( 'wp_footer', [ $this, 'display_geolocation_currency_update_notice' ] );
		}

		// Skip recalculating the cart to prevent infinite loop in simulation.
		remove_action( 'wp_loaded', [ $this, 'recalculate_cart' ] );
	}

	/**
	 * Returns whether the simulation querystring param is set and active
	 *
	 * @return  bool  Whether the simulation is enabled or not
	 */
	public function is_simulation_enabled() {
		return 0 < count( $this->simulation_params );
	}

	/**
	 * Gets the Multi-Currency onboarding preview overrides from the querystring.
	 *
	 * @return  array  Override variables
	 */
	public function get_multi_currency_onboarding_simulation_variables() {

		$parameters = $_GET; // phpcs:ignore WordPress.Security.NonceVerification
		// Check if we are in a preview session, don't interfere with the main session.
		if ( ! isset( $parameters['is_mc_onboarding_simulation'] ) || ! (bool) $parameters['is_mc_onboarding_simulation'] ) {
			// Check if the page referer has the variables.
			$server = $_SERVER; // phpcs:ignore WordPress.Security.NonceVerification
			// Check if we are coming from a simulation session (if we don't have the necessary query strings).
			if ( isset( $server['HTTP_REFERER'] ) && 0 < strpos( $server['HTTP_REFERER'], 'is_mc_onboarding_simulation' ) ) {
				wp_parse_str( wp_parse_url( $server['HTTP_REFERER'], PHP_URL_QUERY ), $parameters );
				if ( ! isset( $parameters['is_mc_onboarding_simulation'] ) || ! (bool) $parameters['is_mc_onboarding_simulation'] ) {
					return [];
				}
			} else {
				return [];
			}
		}

		// Define variables which can be overridden inside the preview session, with their sanitization methods.
		$possible_variables = [
			'enable_storefront_switcher' => 'wp_validate_boolean',
			'enable_auto_currency'       => 'wp_validate_boolean',
		];

		// Define the defaults if the parameter is missing in the request.
		$defaults = [
			'enable_storefront_switcher' => false,
			'enable_auto_currency'       => false,
		];

		// Prepare the params array.
		$values = [];

		// Walk through the querystring parameter possibilities, and prepare the params.
		foreach ( $possible_variables as $possible_variable => $sanitization_callback ) {
			// phpcs:disable WordPress.Security.NonceVerification
			if ( isset( $parameters[ $possible_variable ] ) ) {
				$values[ $possible_variable ] = $sanitization_callback( $parameters[ $possible_variable ] );
			} else {
				// Append the default, the param is missing in the querystring.
				$values [ $possible_variable ] = $defaults[ $possible_variable ];
			}
		}

		return $values;
	}

	/**
	 * Adds the required querystring parameters to all urls in preview pages.
	 *
	 * @return  void
	 */
	private function add_simulation_params_to_preview_urls() {
		$params = $this->simulation_params;
		add_filter(
			'wp_footer',
			function () use ( $params ) {
				?>
			<script type="text/javascript" id="wcpay_multi_currency-simulation-script">
				// Add simulation overrides to all links.
				document.querySelectorAll('a').forEach((link) => {
					const parsedURL = new URL(link.href);
					if (
						false === parsedURL.searchParams.has( 'is_mc_onboarding_simulation' )
					) {
						parsedURL.searchParams.set('is_mc_onboarding_simulation', true);
						parsedURL.searchParams.set('enable_auto_currency', <?php echo esc_attr( $params['enable_auto_currency'] ? 'true' : 'false' ); ?>);
						parsedURL.searchParams.set('enable_storefront_switcher', <?php echo esc_attr( $params['enable_storefront_switcher'] ? 'true' : 'false' ); ?>);
						link.href = parsedURL.toString();
					}
				});

				// Unhide the store notice in simulation mode.
				document.addEventListener('DOMContentLoaded', () => {
					const noticeElement = document.querySelector('.woocommerce-store-notice.demo_store')
					if( noticeElement ) {
						const noticeId = noticeElement.getAttribute('data-notice-id');
						cookieStore.delete( 'store_notice' + noticeId );
					}
				});
			</script>
				<?php
			}
		);
	}

	/**
	 * Checks if the currently displayed page is the WooCommerce Payments
	 * settings page for the Multi-Currency settings.
	 *
	 * @return bool
	 */
	public function is_multi_currency_settings_page(): bool {
		global $current_screen, $current_tab;
		return (
			is_admin()
			&& $current_tab && $current_screen
			&& 'wcpay_multi_currency' === $current_tab
			&& 'woocommerce_page_wc-settings' === $current_screen->base
		);
	}

	/**
	 * Get all the currencies that have been used in the store.
	 *
	 * @return array
	 */
	public function get_all_customer_currencies(): array {
		global $wpdb;

		$currencies = get_option( self::CUSTOMER_CURRENCIES_KEY );

		if ( self::is_customer_currencies_data_valid( $currencies ) ) {
			return array_map( 'strtoupper', $currencies );
		}

		$currencies  = $this->get_available_currencies();
		$query_union = [];

		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) &&
					\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			foreach ( $currencies as $currency ) {
				$query_union[] = $wpdb->prepare(
					"SELECT %s AS currency_code, EXISTS(SELECT currency FROM {$wpdb->prefix}wc_orders WHERE currency=%s LIMIT 1) AS exists_in_orders",
					$currency->code,
					$currency->code
				);
			}
		} else {
			foreach ( $currencies as $currency ) {
				$query_union[] = $wpdb->prepare(
					"SELECT %s AS currency_code, EXISTS(SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key=%s AND meta_value=%s LIMIT 1) AS exists_in_orders",
					$currency->code,
					'_order_currency',
					$currency->code
				);
			}
		}

		$sub_query  = implode( ' UNION ALL ', $query_union );
		$query      = "SELECT currency_code FROM ( $sub_query ) as subquery WHERE subquery.exists_in_orders=1 ORDER BY currency_code ASC";
		$currencies = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( self::is_customer_currencies_data_valid( $currencies ) ) {
			update_option( self::CUSTOMER_CURRENCIES_KEY, $currencies );
			return array_map( 'strtoupper', $currencies );
		}

		return [];
	}

	/**
	 * Checks if there are additional currencies enabled beyond the store's default one.
	 *
	 * @return bool
	 */
	public function has_additional_currencies_enabled(): bool {
		$enabled_currencies = $this->get_enabled_currencies();
		return count( $enabled_currencies ) > 1;
	}

	/**
	 * Returns if the currency initializations are completed.
	 *
	 * @return  bool    If the initializations have been completed.
	 */
	public function is_initialized(): bool {
		return static::$is_initialized;
	}

	/**
	 * Logs a message and throws InvalidCurrencyException.
	 *
	 * @param string $method        The method that's actually throwing the exception.
	 * @param string $currency_code The currency code that was invalid.
	 * @param int    $code          The exception code.
	 *
	 * @throws InvalidCurrencyException
	 */
	private function log_and_throw_invalid_currency_exception( $method, $currency_code, $code = 500 ) {
		$message = 'Invalid currency passed to ' . $method . ': ' . $currency_code;
		Logger::error( $message );
		throw new InvalidCurrencyException( esc_html( $message ), 'wcpay_multi_currency_invalid_currency', esc_html( $code ) );
	}

	/**
	 * Checks if the customer currencies data is valid.
	 *
	 * @param mixed $currencies The currencies to check.
	 *
	 * @return bool
	 */
	private function is_customer_currencies_data_valid( $currencies ) {
		return ! empty( $currencies ) && is_array( $currencies );
	}

	/**
	 * Sets the cache cookie for currency code and exchange rate.
	 *
	 * This private method sets the 'wcpay_currency' cookie if HTTP headers
	 * have not been sent. This cookie stores the selected currency's code and its exchange rate,
	 * and is intended exclusively for caching purposes, not for application logic.
	 */
	private function maybe_add_cache_cookie() {
		if ( ! headers_sent() && ! is_admin() && ! defined( 'DOING_CRON' ) && ! Utils::is_admin_api_request() ) {
			wc_setcookie( 'wcpay_currency', sprintf( '%s_%s', $this->get_selected_currency()->get_code(), $this->get_selected_currency()->get_rate() ), time() + HOUR_IN_SECONDS );
		}
	}
}
