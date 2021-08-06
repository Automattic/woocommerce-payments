<?php
/**
 * Class MultiCurrency
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency;

use WC_Payments;
use WC_Payments_Account;
use WC_Payments_API_Client;
use WC_Payments_Localization_Service;
use WCPay\Exceptions\API_Exception;
use WCPay\MultiCurrency\Notes\NoteMultiCurrencyAvailable;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi Currency functionality.
 */
class MultiCurrency {

	const CURRENCY_SESSION_KEY            = 'wcpay_currency';
	const CURRENCY_META_KEY               = 'wcpay_currency';
	const CURRENCY_CACHE_OPTION           = 'wcpay_multi_currency_cached_currencies';
	const CURRENCY_RETRIEVAL_ERROR_OPTION = 'wcpay_multi_currency_retrieval_error';

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
	 * Utils instance.
	 *
	 * @var Utils
	 */
	protected $utils;

	/**
	 * Analytics instance.
	 *
	 * @var Analytics
	 */
	protected $analytics;

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
	 * Main MultiCurrency Instance.
	 *
	 * Ensures only one instance of MultiCurrency is loaded or can be loaded.
	 *
	 * @static
	 * @return MultiCurrency - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self( WC_Payments::get_payments_api_client(), WC_Payments::get_account_service(), WC_Payments::get_localization_service() );
		}
		return self::$instance;
	}

	/**
	 * Class constructor.
	 *
	 * @param WC_Payments_API_Client           $payments_api_client  Payments API client.
	 * @param WC_Payments_Account              $payments_account     Payments Account instance.
	 * @param WC_Payments_Localization_Service $localization_service Localization Service instance.
	 */
	public function __construct( WC_Payments_API_Client $payments_api_client, WC_Payments_Account $payments_account, WC_Payments_Localization_Service $localization_service ) {
		$this->payments_api_client  = $payments_api_client;
		$this->payments_account     = $payments_account;
		$this->localization_service = $localization_service;
		$this->geolocation          = new Geolocation( $this->localization_service );
		$this->utils                = new Utils();
		$this->compatibility        = new Compatibility( $this, $this->utils );
		$this->analytics            = new Analytics( $this );

		if ( is_admin() ) {
			add_filter( 'woocommerce_get_settings_pages', [ $this, 'init_settings_pages' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		}

		add_action( 'init', [ $this, 'init' ] );
		add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
		add_action( 'widgets_init', [ $this, 'init_widgets' ] );

		$is_frontend_request = ! is_admin() && ! defined( 'DOING_CRON' ) && ! WC()->is_rest_api_request();

		if ( $is_frontend_request ) {
			// Make sure that this runs after the main init function.
			add_action( 'init', [ $this, 'update_selected_currency_by_url' ], 11 );
			add_action( 'init', [ $this, 'update_selected_currency_by_geolocation' ], 12 );
		}
	}

	/**
	 * Called after the WooCommerce session has been initialized. Initialises the available currencies,
	 * default currency and enabled currencies for the multi currency plugin.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! $this->payments_account->is_stripe_connected() ) {
			return;
		}

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

		new PaymentMethodsCompatibility( $this, WC_Payments::get_gateway() );
		new AdminNotices();
		new UserSettings( $this );

		$this->frontend_prices     = new FrontendPrices( $this, $this->compatibility );
		$this->frontend_currencies = new FrontendCurrencies( $this, $this->localization_service );
		$this->backend_currencies  = new BackendCurrencies( $this, $this->localization_service );

		add_action( 'woocommerce_order_refunded', [ $this, 'add_order_meta_on_refund' ], 50, 2 );

		// Check to make sure there are enabled currencies, then for Storefront being active, and then load the integration.
		$theme = wp_get_theme();
		if ( 'storefront' === $theme->get_stylesheet() || 'storefront' === $theme->get_template() ) {
			$this->storefront_integration = new StorefrontIntegration( $this );
		}

		if ( is_admin() ) {
			add_action( 'admin_init', [ __CLASS__, 'add_woo_admin_notes' ] );
		}
	}

	/**
	 * Initialize the REST API controller.
	 *
	 * @return void
	 */
	public function init_rest_api() {
		if ( ! $this->payments_account->is_stripe_connected() ) {
			return;
		}

		$api_controller = new RestController( \WC_Payments::create_api_client() );
		$api_controller->register_routes();
	}

	/**
	 * Initialize the Widgets.
	 *
	 * @return void
	 */
	public function init_widgets() {
		if ( ! $this->payments_account->is_stripe_connected() ) {
			return;
		}

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
		if ( $this->payments_account->is_stripe_connected() ) {
			$settings_pages[] = new Settings( $this );
		} else {
			$settings_pages[] = new SettingsOnboardCta( $this );
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

		// Output the settings JS and CSS only on the settings page.
		if ( 'wcpay_multi_currency' === $current_tab ) {
			$this->register_admin_scripts();
			wp_enqueue_script( 'WCPAY_MULTI_CURRENCY_SETTINGS' );
			wp_enqueue_style( 'WCPAY_MULTI_CURRENCY_SETTINGS' );
		}
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

		$error_expires = get_option( self::CURRENCY_RETRIEVAL_ERROR_OPTION, 0 );
		$cache_data    = $this->read_currencies_from_cache();

		// If the error has not expired, return cached data or null and do not attempt another API call.
		if ( $error_expires > time() ) {
			return $cache_data ?? null;
		}

		// If an array of currencies was returned from the cache and has not expired, return it here.
		if ( null !== $cache_data && $cache_data['expires'] > time() ) {
			return $cache_data;
		}

		// If connection to server cannot be established, return expired data or null.
		if ( ! $this->payments_api_client->is_server_connected() ) {
			return $cache_data ?? null;
		}

		// If the cache was expired or something went wrong, make a call to the server to get the currency data.
		try {
			$currency_data = $this->payments_api_client->get_currency_rates( get_woocommerce_currency() );
		} catch ( API_Exception $e ) {
			// Failed to retrieve currencies from the server. Exception is logged in http client.
			// Rate limit for a short amount of time by persisting the failure.
			update_option( self::CURRENCY_RETRIEVAL_ERROR_OPTION, time() + 1 * MINUTE_IN_SECONDS, 'no' );

			// Return expired data or null to signal currency retrieval error.
			return $cache_data ?? null;
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
	 * Returns the Currency Switcher Widget instance.
	 *
	 * @return CurrencySwitcherWidget
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
	 * @param string[] $currencies Array of currency codes to be enabled.
	 *
	 * @return void
	 */
	public function set_enabled_currencies( $currencies = [] ) {
		if ( 0 < count( $currencies ) ) {
			// Get the currencies that were removed before they are updated.
			$removed_currencies = array_diff( array_keys( $this->enabled_currencies ), $currencies );

			// Update the enabled currencies and reinitialize.
			update_option( $this->id . '_enabled_currencies', $currencies );
			$this->initialize_enabled_currencies();

			// Now remove the removed currencies settings.
			$this->remove_currencies_settings( $removed_currencies );
		}
	}

	/**
	 * Gets the user selected currency, or `$default_currency` if is not set.
	 *
	 * @return Currency
	 */
	public function get_selected_currency(): Currency {
		$code = $this->get_stored_currency_code();

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
		if ( ! $this->payments_account->is_stripe_connected() ) {
			return;
		}

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
		if ( ! $this->payments_account->is_stripe_connected() ) {
			return;
		}

		// We only want to automatically set the currency if it's already not set.
		if ( $this->is_using_auto_currency_switching() && ! $this->get_stored_currency_code() ) {
			$currency = $this->geolocation->get_currency_by_customer_location();

			// Update currency and display notice if enabled.
			if ( ! empty( $this->get_enabled_currencies()[ $currency ] ) ) {
				$this->update_selected_currency( $currency );
				add_action( 'wp_footer', [ $this, 'display_geolocation_currency_update_notice' ] );
			}
		}
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
		$current_currency = $this->get_selected_currency();
		$store_currency   = get_option( 'woocommerce_currency' );
		$country          = $this->geolocation->get_country_by_customer_location();
		$currencies       = get_woocommerce_currencies();

		// Do not display notice if using the store's default currency.
		if ( $store_currency === $current_currency->get_code() ) {
			return;
		}

		$message = sprintf(
			/* translators: %1 User's country, %2 Selected currency name, %3 Default store currency name */
			__( 'We noticed you\'re visiting from %1$s. We\'ve updated our prices to %2$s for your shopping convenience. <a>Use %3$s instead.</a>', 'woocommerce-payments' ),
			WC()->countries->countries[ $country ],
			$current_currency->get_name(),
			$currencies[ $store_currency ]
		);

		$notice_id = md5( $message );

		echo '<p class="woocommerce-store-notice demo_store" data-notice-id="' . esc_attr( $notice_id . 2 ) . '" style="display:none;">';
		echo \WC_Payments_Utils::esc_interpolated_html(
			$message,
			[
				'a' => '<a href="?currency=' . $store_currency . '">',
			]
		);
		echo ' <a href="#" class="woocommerce-store-notice__dismiss-link">' . esc_html__( 'Dismiss', 'woocommerce-payments' ) . '</a></p>';
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
	 * Returns the currency code stored for the user or in the session.
	 *
	 * @return string|null Currency code.
	 */
	private function get_stored_currency_code() {
		$user_id = get_current_user_id();
		$code    = null;

		if ( 0 === $user_id && WC()->session ) {
			$code = WC()->session->get( self::CURRENCY_SESSION_KEY );
		} elseif ( $user_id ) {
			$code = get_user_meta( $user_id, self::CURRENCY_META_KEY, true );
		}

		return $code;
	}

	/**
	 * Caches currency data for a period of time.
	 *
	 * @param array    $currencies - Currency data to cache.
	 * @param int|null $updated    - The time the data was fetched from the server.
	 * @param int|null $expiration - The length of time to cache the currency data, in seconds.
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

		return update_option( self::CURRENCY_CACHE_OPTION, $currency_cache, 'no' );
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
		$currency_cache = get_option( self::CURRENCY_CACHE_OPTION, [] );

		if ( ! isset( $currency_cache['currencies'] ) || ! isset( $currency_cache['expires'] ) || ! isset( $currency_cache['updated'] ) ) {
			// No option found or the data isn't in the format we expect.
			return null;
		}

		// We have fresh currency data in the cache, so return it.
		return $currency_cache;
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
		if ( 3 !== strlen( $code ) || isset( $this->enabled_currencies[ $code ] ) ) {
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
		return apply_filters( 'wcpay_multi_currency_available_currencies', array_intersect( $account_currencies, $wc_currencies ) );
	}

	/**
	 * Checks if the merchant has enabled automatic currency switching and geolocation.
	 *
	 * @return bool
	 */
	private function is_using_auto_currency_switching(): bool {
		return 'yes' === get_option( $this->id . '_enable_auto_currency', false );
	}

	/**
	 * Register the CSS and JS admin scripts.
	 *
	 * @return void
	 */
	private function register_admin_scripts() {
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
}
