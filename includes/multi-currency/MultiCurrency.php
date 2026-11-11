<?php
/**
 * Class MultiCurrency
 *
 * @package WooCommerce\Payments\MultiCurrency
 */

namespace WCPay\MultiCurrency;

use WCPay\MultiCurrency\Exceptions\InvalidCurrencyException;
use WCPay\MultiCurrency\Exceptions\InvalidCurrencyRateException;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyAccountInterface;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyApiClientInterface;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyCacheInterface;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyLocalizationInterface;
use WCPay\MultiCurrency\Interfaces\MultiCurrencySettingsInterface;
use WCPay\MultiCurrency\Logger;
use WCPay\MultiCurrency\Notes\NoteMultiCurrencyAvailable;
use WCPay\MultiCurrency\Utils;
use WC_Payments_Features;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi-Currency functionality.
 */
class MultiCurrency {

	const CURRENCY_SESSION_KEY    = 'wcpay_currency';
	const CURRENCY_META_KEY       = 'wcpay_currency';
	const FILTER_PREFIX           = 'wcpay_multi_currency_';
	const CUSTOMER_CURRENCIES_KEY = 'wcpay_multi_currency_stored_customer_currencies';
	const RENDERING_MODE_SPEED    = 'speed';
	const RENDERING_MODE_CACHE    = 'cache';

	/**
	 * Option that stores the multi-currency price rendering mode ('speed' or 'cache').
	 */
	const RENDERING_MODE_OPTION = 'wcpay_multi_currency_rendering_mode';

	/**
	 * Session key that stores the currency the saved cart totals were calculated in.
	 */
	const CART_TOTALS_CURRENCY_SESSION_KEY = 'wcpay_cart_totals_currency';

	/**
	 * Session key of a hash of the saved cart totals the currency stamp was recorded for. Totals saved while
	 * Multi-Currency didn't run (e.g. while the merchant had it switched off) no longer match it.
	 */
	const CART_TOTALS_HASH_SESSION_KEY = 'wcpay_cart_totals_hash';

	/**
	 * Session key of the stale cart totals state whose cached shipping rates were last dropped, so they are dropped
	 * once per stale state rather than on every load.
	 */
	private const STALE_TOTALS_RATES_DROPPED_SESSION_KEY = 'wcpay_stale_totals_rates_dropped';

	/**
	 * Option flag marking that the one-time caching auto-detection has already run.
	 */
	const CACHE_AUTODETECT_DONE_OPTION = 'wcpay_multi_currency_cache_autodetect_done';

	/**
	 * Option flag marking that the merchant dismissed the cache-mode recommendation notice.
	 */
	const CACHE_RECOMMENDATION_DISMISSED_OPTION = 'wcpay_multi_currency_cache_recommendation_dismissed';

	/**
	 * The plugin's ID.
	 *
	 * @var string
	 */
	public $id = 'wcpay_multi_currency';

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
	 * Instance of MultiCurrencySettingsInterface.
	 *
	 * @var MultiCurrencySettingsInterface
	 */
	private $settings_service;

	/**
	 * Client for making requests to the API
	 *
	 * @var MultiCurrencyApiClientInterface
	 */
	private $payments_api_client;

	/**
	 * Instance of MultiCurrencyAccountInterface.
	 *
	 * @var MultiCurrencyAccountInterface
	 */
	private $payments_account;

	/**
	 * Instance of MultiCurrencyLocalizationInterface.
	 *
	 * @var MultiCurrencyLocalizationInterface
	 */
	private $localization_service;

	/**
	 * Instance of MultiCurrencyCacheInterface.
	 *
	 * @var MultiCurrencyCacheInterface
	 */
	private $cache;

	/**
	 * Tracking instance.
	 *
	 * @var Tracking
	 */
	protected $tracking;

	/**
	 * AsyncPriceRenderer instance.
	 *
	 * @var AsyncPriceRenderer
	 */
	protected $async_renderer;

	/**
	 * CachingEnvironment instance.
	 *
	 * @var CachingEnvironment
	 */
	protected $caching_environment;

	/**
	 * Simulation variables array.
	 *
	 * @var array
	 */
	protected $simulation_params = [];

	/**
	 * Whether init() has already run for this instance.
	 *
	 * Separate from the static $is_initialized, which reports whether Multi-Currency is running
	 * and is shared by every instance in the process. This one guards re-entry per instance.
	 *
	 * @var bool
	 */
	private $init_ran = false;

	/**
	 * Whether initialization completed and frontend hooks can be refreshed.
	 *
	 * @var bool
	 */
	private $init_completed = false;

	/**
	 * Callbacks added for REST API requests that don't look like Store API requests, keyed by filter name, so they
	 * can be removed if a Store API route ends up handling the request.
	 *
	 * @var array<string, callable>
	 */
	private $rest_api_overrides = [];

	/**
	 * The totals WC_Cart_Session::set_session() saved for the main cart during the calculation in progress, or null
	 * if the main cart's totals haven't been saved in it.
	 *
	 * @var array|null
	 */
	private $main_cart_totals_saved_in_calculation = null;

	/**
	 * The main cart while its totals are being calculated, or null outside its calculation.
	 *
	 * @var \WC_Cart|null
	 */
	private $main_cart_in_calculation = null;

	/**
	 * Whether this request selected the currency before the cart loaded (a switch or a `?currency=` link), so the load
	 * must recalculate stale totals.
	 *
	 * @var bool
	 */
	private $currency_changed_before_cart_load = false;

	/**
	 * The REST request that set the Store API flag, so only its own completion clears it, not a nested one's.
	 *
	 * @var \WP_REST_Request|null
	 */
	private $matched_store_api_request = null;

	/**
	 * Class constructor.
	 *
	 * @param MultiCurrencySettingsInterface     $settings_service     Settings service.
	 * @param MultiCurrencyApiClientInterface    $payments_api_client  Payments API client.
	 * @param MultiCurrencyAccountInterface      $payments_account     Payments Account instance.
	 * @param MultiCurrencyLocalizationInterface $localization_service Localization Service instance.
	 * @param MultiCurrencyCacheInterface        $cache                Cache instance.
	 * @param Utils|null                         $utils                Optional Utils instance.
	 * @param CachingEnvironment|null            $caching_environment  Optional CachingEnvironment instance.
	 */
	public function __construct( MultiCurrencySettingsInterface $settings_service, MultiCurrencyApiClientInterface $payments_api_client, MultiCurrencyAccountInterface $payments_account, MultiCurrencyLocalizationInterface $localization_service, MultiCurrencyCacheInterface $cache, ?Utils $utils = null, ?CachingEnvironment $caching_environment = null ) {
		$this->settings_service     = $settings_service;
		$this->payments_api_client  = $payments_api_client;
		$this->payments_account     = $payments_account;
		$this->localization_service = $localization_service;
		$this->cache                = $cache;
		// If a Utils instance is not passed as argument, initialize it. This allows to mock it in tests.
		$this->utils                   = $utils ?? new Utils();
		$this->caching_environment     = $caching_environment ?? new CachingEnvironment();
		$this->geolocation             = new Geolocation( $this->localization_service );
		$this->compatibility           = new Compatibility( $this, $this->utils );
		$this->currency_switcher_block = new CurrencySwitcherBlock( $this, $this->compatibility );
		$this->async_renderer          = new AsyncPriceRenderer( $this );
	}

	/**
	 * Backwards compatibility for the old `instance()` static method.
	 *
	 * We need to use this as some plugins still call `MultiCurrency::instance()` directly.
	 *
	 * @return null|MultiCurrency - Main instance.
	 */
	public static function instance() {
		if ( function_exists( 'WC_Payments_Multi_Currency' ) ) {
			return WC_Payments_Multi_Currency();
		}
	}

	/**
	 * Whether the Multi-Currency module may initialize on this request.
	 *
	 * True when the customer multi-currency feature is enabled, or when the merchant is going through the
	 * Multi-Currency setup flow, which needs the module before the feature is switched on. Everything that
	 * boots the module — the `plugins_loaded` bootstrap, the lazy-initializing getters, and direct callers of
	 * `WC_Payments_Multi_Currency()` — must respect this, so a disabled module never registers hooks or
	 * touches the currency, no matter who asks for it.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		if ( WC_Payments_Features::is_customer_multi_currency_enabled() ) {
			return true;
		}

		return function_exists( 'wcpay_multi_currency_onboarding_check' ) && wcpay_multi_currency_onboarding_check();
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
			add_action( 'admin_init', [ $this, 'maybe_auto_enable_cache_rendering_mode' ] );
		}

		add_action( 'init', [ $this, 'init' ] );
		add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
		add_action( 'widgets_init', [ $this, 'init_widgets' ] );
		add_action( 'woocommerce_before_calculate_totals', [ $this, 'start_cart_totals_calculation' ], PHP_INT_MIN );
		add_action( 'woocommerce_cart_updated', [ $this, 'note_cart_totals_saved' ], PHP_INT_MAX );
		// Stamp saved totals with their currency in every context, so stale totals are detectable on load.
		// Runs after WC_Cart_Session::set_session() (priority 1000) saves the totals.
		add_action( 'woocommerce_after_calculate_totals', [ $this, 'record_cart_totals_currency' ], PHP_INT_MAX );
		add_action( 'woocommerce_cart_emptied', [ $this, 'clear_cart_totals_currency' ] );

		$is_frontend_request = ! is_admin() && ! defined( 'DOING_CRON' ) && ! Utils::is_admin_api_request();

		if ( $is_frontend_request || Utils::is_store_api_request() ) {
			// Make sure that this runs after the main init function.
			add_action( 'init', [ $this, 'update_selected_currency_by_url' ], 11 );
			add_action( 'init', [ $this, 'update_selected_currency_by_geolocation' ], 12 );
			add_action( 'init', [ $this, 'possible_simulation_activation' ], 13 );
			add_action( 'woocommerce_created_customer', [ $this, 'set_new_customer_currency_meta' ] );
			// get_woocommerce_currency() reflects the selected currency only here; the late priority runs after other cart-loaded callbacks.
			add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'maybe_recalculate_cart_for_currency' ], PHP_INT_MAX );
		}

		if ( WC()->is_rest_api_request() ) {
			if ( ! Utils::is_store_batch_request() && ! Utils::is_store_api_request() ) {
				$this->rest_api_overrides = isset( $_GET['currency'] ) // phpcs:ignore WordPress.Security.NonceVerification
					? [
						self::FILTER_PREFIX . 'override_selected_currency' => function () {
							return strtoupper( sanitize_text_field( wp_unslash( $_GET['currency'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
						},
					]
					: [
						// Non-Store-API REST: default to the store currency, leave prices as-is.
						self::FILTER_PREFIX . 'should_return_store_currency' => function () {
							return true;
						},
						self::FILTER_PREFIX . 'should_convert_product_price' => function () {
							return false;
						},
						self::FILTER_PREFIX . 'override_selected_currency' => function () {
							return $this->get_default_currency()->get_code();
						},
					];
				foreach ( $this->rest_api_overrides as $filter => $callback ) {
					add_filter( $filter, $callback );
				}
			}
		}

		if ( WC()->is_rest_api_request() || ! empty( $_POST['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			// The URL doesn't always name the route, so undo the overrides once a Store API route matches.
			// Priority 9: before other hooks here read the currency.
			add_filter( 'rest_request_before_callbacks', [ $this, 'maybe_restore_selected_currency_for_store_api_route' ], 9, 3 );
		}

		add_filter( 'wcpay_payment_fields_js_config', [ $this, 'add_props_to_wcpay_js_config' ] );

		$this->currency_switcher_block->init_hooks();
	}

	/**
	 * Treats the request as a Store API request once WordPress has matched it, or a request batched in it, to a
	 * Store API route, even though its URL didn't look like one (see `Utils::set_matched_store_api_route()`).
	 *
	 * Store API routes load and save the shopper's cart, so they must run in the shopper's selected currency:
	 * the store-currency overrides that `init_hooks()` sets up for other REST API requests are removed, and the
	 * frontend price and currency hooks are registered as they would be for a Store API request.
	 *
	 * @param mixed           $response Result to send to the client.
	 * @param mixed           $handler  Route handler used for the request.
	 * @param WP_REST_Request $request  Request used to generate the response.
	 *
	 * @return mixed The unchanged response.
	 */
	public function maybe_restore_selected_currency_for_store_api_route( $response, $handler, $request ) {
		// Namespace hard-coded (WC's Store API list isn't public API). Add `wc/ucp/` and any future Store API
		// namespace here when it ships in a supported WC version.
		if ( Utils::is_store_api_request() || 0 !== stripos( $request->get_route(), '/wc/store/' ) ) {
			return $response;
		}

		// Set the flag before the hooks below, which read it.
		Utils::set_matched_store_api_route( true );

		// Clear the flag when this request finishes — a nested Store API request (e.g. WooPay building its session)
		// must not leave the surrounding request reclassified. `rest_request_after_callbacks` also fires for nested
		// requests, so remember which request set the flag and let only that one clear it.
		$this->matched_store_api_request = $request;
		add_filter( 'rest_request_after_callbacks', [ $this, 'reset_matched_store_api_route' ], 10, 3 );

		// Remove by reference (not by name) the overrides this instance added.
		foreach ( $this->rest_api_overrides as $filter => $callback ) {
			remove_filter( $filter, $callback );
		}
		$this->rest_api_overrides = [];

		add_action( 'woocommerce_created_customer', [ $this, 'set_new_customer_currency_meta' ] );

		// Enable the frontend price/currency hooks `init_hooks()` skipped, and select the shopper's currency.
		// `?currency=` isn't re-applied here: `update_selected_currency_by_url()` already saved it where it applies.
		//
		// Select twice, because the session that renders the response depends on the request type: a cookie-session
		// request already has its session active, but a Cart-Token Store API request loads its session later, in the
		// route callback (`AbstractCartRoute::load_cart_session()`). The inline call reaches the former;
		// `woocommerce_cart_loaded_from_session` reaches the latter, after its cart items load. Geolocation no-ops once a
		// currency is set, so running on both is harmless.
		if ( $this->init_completed ) {
			$this->enable_server_price_hooks();
			$this->update_selected_currency_by_geolocation();
			add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'select_currency_for_matched_store_api_route' ] );
			// `init_hooks()` skips this while the request still looks like an admin API one.
			add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'maybe_recalculate_cart_for_currency' ], PHP_INT_MAX );
		}

		return $response;
	}

	/**
	 * Resets the "matched a Store API route" flag once the matched route's callbacks have run, so it does not
	 * outlive the REST request that set it (see `maybe_restore_selected_currency_for_store_api_route()`).
	 *
	 * Only the flag is restored, by design: the shopper-currency hooks and removed overrides stay, so the rest of the
	 * request (and later `batch/v1` sub-requests) keep using the shopper's currency rather than the store default.
	 * Scoping the reset to restore per sub-request would instead run those later sub-requests in the store default.
	 *
	 * The deferred cart-load selection is also detached here so it does not outlive the route that armed it (for
	 * example a route that never loads the cart).
	 *
	 * @param mixed                 $response Result to send to the client.
	 * @param mixed                 $handler  Route handler used for the request.
	 * @param \WP_REST_Request|null $request The request whose callbacks just ran.
	 *
	 * @return mixed The unchanged response.
	 */
	public function reset_matched_store_api_route( $response, $handler = null, $request = null ) {
		// Only the request that set the flag clears it; a nested request completing inside it leaves it set.
		if ( null !== $request && $request !== $this->matched_store_api_request ) {
			return $response;
		}

		Utils::set_matched_store_api_route( false );
		$this->matched_store_api_request = null;
		remove_action( 'woocommerce_cart_loaded_from_session', [ $this, 'select_currency_for_matched_store_api_route' ] );
		remove_filter( 'rest_request_after_callbacks', [ $this, 'reset_matched_store_api_route' ], 10 );
		return $response;
	}

	/**
	 * Re-selects the shopper's currency once the cart has loaded from its session, on the Cart-Token session that
	 * renders the response. Armed by `maybe_restore_selected_currency_for_store_api_route()` (which explains the
	 * two-step selection) and detached by `reset_matched_store_api_route()`.
	 *
	 * @return void
	 */
	public function select_currency_for_matched_store_api_route() {
		// The currency was resolved from the session before this one, and geolocation no-ops when this one has its own.
		$this->frontend_currencies->selected_currency_changed();
		$this->update_selected_currency_by_geolocation();
	}

	/**
	 * Called after the WooCommerce session has been initialized. Initialises the available currencies,
	 * default currency and enabled currencies for the Multi-Currency plugin.
	 *
	 * @return void
	 */
	public function init() {
		// init() runs on the `init` action and lazily from the getters below. A caller that reaches
		// a getter before `init` fires would otherwise initialize twice, leaving two sets of price
		// and currency objects hooked at the same priority.
		if ( $this->init_ran ) {
			// In cache-optimized mode, a getter can trigger init() before the WooCommerce session
			// exists, which selects async rendering. Once the session is available, switch to
			// server-side prices so the visitor's selected currency is honoured for the rest of the
			// request. Outside cache-optimized mode the first call already registered server-side
			// hooks, so a repeated call leaves them alone.
			if ( $this->init_completed && $this->is_cache_optimized_mode() && ! $this->should_use_async_rendering() ) {
				$this->enable_server_price_hooks();
			}
			return;
		}

		$this->init_ran = true;

		// The lazy-initializing getters call init() on demand, and any code path can reach them through
		// WC_Payments_Multi_Currency() regardless of the feature flag. Refuse to initialize when the module
		// is disabled, so a disabled module never registers the frontend currency and price hooks.
		if ( ! static::is_enabled() ) {
			$this->set_inert_state();
			return;
		}

		// If the store currency is not in the list of available WooCommerce currencies
		// (e.g. a custom currency was removed), bail out to avoid fatal errors.
		// Multi-Currency cannot function without a valid base currency.
		$store_currency = $this->get_store_currency_code();
		if ( ! array_key_exists( $store_currency, get_woocommerce_currencies() ) ) {
			Logger::error(
				sprintf(
					'Multi-Currency disabled: store currency "%s" is not a recognized WooCommerce currency. '
					. 'A custom currency may have been removed. Update the currency at WooCommerce → Settings → General.',
					$store_currency
				)
			);
			$this->set_inert_state();
			return;
		}

		$store_currency_updated = $this->check_store_currency_for_change();

		// If the store currency has been updated, invalidate the exchange rate cache
		// before initializing currencies so fresh rates are fetched immediately.
		if ( $store_currency_updated ) {
			$this->cache->delete( MultiCurrencyCacheInterface::CURRENCIES_KEY );
		}

		$this->initialize_available_currencies();
		$this->set_default_currency();
		$this->initialize_enabled_currencies();

		// If the store currency has been updated, we need to update the notice that will display any manual currencies.
		if ( $store_currency_updated ) {
			$this->update_manual_rate_currencies_notice_option();
		}

		$admin_notices = new AdminNotices();
		$user_settings = new UserSettings( $this );
		$analytics     = new Analytics( $this, $this->settings_service );

		$this->build_frontend_objects();
		$this->tracking = new Tracking( $this );

		// Init all the hooks.
		$admin_notices->init_hooks();
		$user_settings->init_hooks();
		$analytics->init_hooks();

		// Use async (client-side) rendering only when should_use_async_rendering()
		// is true: cache-optimized mode, no active session, and not a Store API
		// request. Otherwise, use server-side FrontendPrices/FrontendCurrencies.
		// A ?currency= URL param forces server-side conversion (session will be
		// created at init priority 11). Auto-switching is also required: without
		// it, session-less visitors always see the default currency, so skeletons
		// add unnecessary JS latency with no benefit.
		$has_pending_currency_switch = isset( $_GET['currency'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$use_async_rendering         = $this->should_use_async_rendering();

		if ( $use_async_rendering && ! $has_pending_currency_switch && $this->is_using_auto_currency_switching() ) {
			$this->async_renderer->init_hooks();
		}

		if ( ! $use_async_rendering || $has_pending_currency_switch ) {
			$this->enable_server_price_hooks();
		}

		$this->tracking->init_hooks();

		add_action( 'woocommerce_order_refunded', [ $this, 'add_order_meta_on_refund' ], 50, 2 );

		// Check to make sure there are enabled currencies, then for Storefront being active, and then load the integration.
		$theme = wp_get_theme();
		if ( 'storefront' === $theme->get_stylesheet() || 'storefront' === $theme->get_template() ) {
			$this->storefront_integration = new StorefrontIntegration( $this );
		}

		if ( is_admin() ) {
			add_action( 'admin_init', [ $this, 'add_woo_admin_notes' ] );
		}

		// Update the customer currencies option after an order status change.
		add_action( 'woocommerce_order_status_changed', [ $this, 'maybe_update_customer_currencies_option' ] );

		static::$is_initialized = true;
		$this->init_completed   = true;
	}

	/**
	 * Initialize the REST API controller.
	 *
	 * @return void
	 */
	public function init_rest_api() {
		// Catch fatals from controller constructor signature changes when old and new code mix
		// during a manual plugin update. Routes must otherwise always register: internal REST
		// requests dispatched with an admin screen set 404 without them.
		try {
			$api_controller = new RestController( $this );
			$api_controller->register_routes();
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to register REST controller: ' . $e->getMessage() );
		}
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
		// We don't need to check if the payment provider is connected for the
		// Settings page generation on the incoming CLI and async job calls.
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'WPCOM_JOBS' ) && WPCOM_JOBS ) ) {
			return $settings_pages;
		}

		// Due to autoloader limitations, we shouldn't initiate MCCY settings if the plugin was just upgraded:
		// https://github.com/Automattic/woocommerce-payments/issues/9676.
		if ( did_action( 'upgrader_process_complete' ) ) {
			return $settings_pages;
		}

		if ( $this->payments_account->is_provider_connected() ) {
			$settings = new Settings( $this );
			$settings->init_hooks();

			$settings_pages[] = $settings;
		} else {
			$settings_onboard_cta = new SettingsOnboardCta( $this, $this->payments_account );
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
		wp_enqueue_style( 'WCPAY_MULTI_CURRENCY_SETTINGS' );
	}

	/**
	 * Add multi-currency specific props to the WCPay JS config.
	 *
	 * @param  array $config The JS config that will be loaded on the frontend.
	 *
	 * @return array  The updated JS config.
	 */
	public function add_props_to_wcpay_js_config( $config ) {
		$config['isMultiCurrencyEnabled'] = true;

		return $config;
	}

	/**
	 * Gets and caches the data for the currency rates from the server.
	 * Will be returned as an array with two keys:
	 * - 'currencies' (the currencies)
	 * - 'updated' (when this data was fetched from the API).
	 *
	 * @return ?array
	 */
	public function get_cached_currencies() {
		// If connection to server cannot be established, payment provider is not connected, or the account is rejected,
		// return any data we have cached (expired or not) or null.
		if ( ! $this->payments_api_client->is_server_connected() || ! $this->payments_account->is_provider_connected() || $this->payments_account->is_account_rejected() ) {
			$cached_data = $this->cache->get( MultiCurrencyCacheInterface::CURRENCIES_KEY, true );
			return $cached_data;
		}

		return $this->cache->get_or_add(
			MultiCurrencyCacheInterface::CURRENCIES_KEY,
			function () {
				try {
					$currency_data = $this->payments_api_client->get_currency_rates( strtolower( $this->get_store_currency_code() ) );
					return [
						'currencies' => $currency_data,
						'updated'    => time(),
					];
				} catch ( \Exception $e ) {
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
		if ( null === $this->frontend_prices ) {
			$this->init();
		}

		return $this->frontend_prices;
	}

	/**
	 * Returns the FrontendCurrencies instance.
	 *
	 * @return FrontendCurrencies
	 */
	public function get_frontend_currencies(): FrontendCurrencies {
		if ( null === $this->frontend_currencies ) {
			$this->init();
		}

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
		global $wp_widget_factory;

		ob_start();

		$currency_switcher_widget = $this->get_currency_switcher_widget();

		if ( ! is_object( $currency_switcher_widget ) ) {
			Logger::notice(
				sprintf(
					'Invalid widget markup. Widget instance must be type object, %s given.',
					gettype( $currency_switcher_widget )
				)
			);

			return ob_get_clean();
		}

		/*
		 * WordPress changed instance widget keys from spl_object_hash() to spl_object_id() in 7.1.
		 * Find the exact registered instance instead of reproducing WordPress's key generation.
		 */
		$widget_key = array_search( $currency_switcher_widget, $wp_widget_factory->widgets, true );

		the_widget(
			$widget_key,
			/**
			 * Filters the instance settings passed to the currency switcher theme widget.
			 *
			 * @since 3.0.0
			 *
			 * @param array $instance The widget instance settings.
			 */
			apply_filters( self::FILTER_PREFIX . 'theme_widget_instance', $instance ),
			/**
			 * Filters the args passed to the currency switcher theme widget.
			 *
			 * @since 3.0.0
			 *
			 * @param array $args The widget display args.
			 */
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
				throw new InvalidCurrencyRateException( esc_html( $message ), 500 );
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

		return $this->default_currency ?? new Currency( $this->localization_service, $this->get_store_currency_code() );
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
	 * Gets an explicit shopper or compatibility currency selection, if one exists.
	 *
	 * Unlike get_selected_currency(), this does not fall back to the store currency. Null means
	 * Multi-Currency has nothing of its own to assert, so a third-party `woocommerce_currency`
	 * filter should be left alone.
	 *
	 * A compatibility override always counts. A stored session or user-meta code only counts when
	 * more than one currency is enabled: with a single currency nothing could have been selected,
	 * so a stored code is stale state (an old `?currency=` link, meta from a time the store had more
	 * currencies), not a choice to assert over other plugins' filters.
	 *
	 * @return Currency|null
	 */
	public function get_explicit_selected_currency(): ?Currency {
		$enabled_currencies = $this->get_enabled_currencies();
		$override_code      = $this->compatibility->override_selected_currency();

		if ( $override_code ) {
			return $enabled_currencies[ $override_code ] ?? null;
		}

		if ( count( $enabled_currencies ) < 2 ) {
			return null;
		}

		$stored_code = $this->get_stored_currency_code();

		if ( null === $stored_code || '' === $stored_code ) {
			return null;
		}

		return $enabled_currencies[ $stored_code ] ?? null;
	}

	/**
	 * Gets the user selected currency, or `$default_currency` if is not set.
	 *
	 * @return Currency
	 */
	public function get_selected_currency(): Currency {
		return $this->get_explicit_selected_currency() ?? $this->get_default_currency();
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

		if ( null === $currency ) {
			return;
		}

		// Don't create a session during async rendering for automatic
		// currency switches (e.g. geolocation). Explicit user switches
		// (persist_change = true) always persist.
		if ( $this->should_use_async_rendering() && ! $persist_change ) {
			return;
		}

		// We discard the cache for the front-end.
		$this->frontend_currencies->selected_currency_changed();

		// initializing the session (useful for Store API),
		// so that the selected currency (set as query string parameter) can be correctly set.
		if ( ! isset( WC()->session ) ) {
			WC()->initialize_session();
		}

		$currency_changed = $this->get_stored_currency_code() !== $code;

		if ( $currency_changed && $persist_change ) {
			$this->frontend_currencies->clear_url_price_params();
		}

		if ( 0 === $user_id && WC()->session ) {
			WC()->session->set( self::CURRENCY_SESSION_KEY, $currency->get_code() );
			// Set the session cookie if not yet set to persist the selected currency.
			if ( ! $this->has_active_session() && ! headers_sent() && $persist_change ) {
				$this->utils->set_customer_session_cookie( true );
			}
		} elseif ( $user_id ) {
			update_user_meta( $user_id, self::CURRENCY_META_KEY, $currency->get_code() );
		}

		// Recalculate now if the cart is already loaded; otherwise the currency stamp lets a later
		// load detect the stale totals (e.g. Store API requests that load the cart later or not at all).
		if ( $currency_changed ) {
			if ( did_action( 'woocommerce_cart_loaded_from_session' ) ) {
				$this->recalculate_cart();
			} else {
				$this->currency_changed_before_cart_load = true;
			}
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

		$code = strtoupper( sanitize_text_field( wp_unslash( $_GET['currency'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$this->update_selected_currency( $code );

		if ( isset( $this->get_enabled_currencies()[ $code ] ) && ! did_action( 'woocommerce_cart_loaded_from_session' ) ) {
			$this->currency_changed_before_cart_load = true;
		}
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

		// Display notice, prevent duplicates in simulation. Registered before the async check below,
		// so unhooking this one callback hides the notice in both rendering modes.
		if ( ! has_action( 'wp_footer', [ $this, 'display_geolocation_currency_update_notice' ] ) ) {
			add_action( 'wp_footer', [ $this, 'display_geolocation_currency_update_notice' ] );
		}

		// When async rendering handles pricing, currency switching is done
		// client-side via the JS renderer. Skip server-side geolocation.
		if ( $this->should_use_async_rendering() ) {
			return;
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
		/**
		 * Filters whether charm pricing should only be applied to products.
		 *
		 * @since 2.6.0
		 *
		 * @param bool $apply_only_to_products Whether to apply charm pricing only to products. Default true.
		 */
		return apply_filters( self::FILTER_PREFIX . 'apply_charm_only_to_products', true );
	}

	/**
	 * Gets the converted price using the current currency with the rounding and charm pricing settings.
	 *
	 * @param mixed         $price    The price to be converted.
	 * @param string        $type     The type of price being converted. One of 'product', 'shipping', 'tax', 'coupon', or 'exchange_rate'.
	 * @param Currency|null $currency The currency to convert the price to. Defaults to the selected currency.
	 *
	 * @return float The converted price.
	 */
	public function get_price( $price, string $type, ?Currency $currency = null ): float {
		$supported_types = [ 'product', 'shipping', 'tax', 'coupon', 'exchange_rate' ];
		$currency        = $currency ?? $this->get_selected_currency();

		if ( ! in_array( $type, $supported_types, true ) || $currency->get_is_default() ) {
			return (float) $price;
		}

		$converted_price = ( (float) $price ) * $currency->get_rate();

		if ( 'tax' === $type || 'coupon' === $type || 'exchange_rate' === $type ) {
			// We must make sure the price is rounded properly before returning it, otherwise we
			// may end up with inconsistent prices in the cart.
			$num_decimals = absint(
				$this->localization_service->get_currency_format(
					$currency->get_code()
				)['num_decimals']
			);
			return round( $converted_price, $num_decimals );
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
			throw new InvalidCurrencyRateException( esc_html( $message ), 500 );
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
		if ( WC()->cart ) {
			WC()->cart->calculate_totals();
		}
	}

	/**
	 * Records the currency the saved cart totals were calculated in, so a later load can detect
	 * stale totals. Runs in every context, after WC_Cart_Session::set_session().
	 *
	 * @param \WC_Cart|null $cart The cart whose totals were calculated.
	 *
	 * @return void
	 */
	public function record_cart_totals_currency( $cart = null ) {
		if ( ! $cart instanceof \WC_Cart || WC()->cart !== $cart || ! isset( WC()->session ) ) {
			return;
		}

		$saved_totals                   = WC()->session->get( 'cart_totals' );
		$this->main_cart_in_calculation = null;

		// Requests that skip saving the cart (e.g. `woocommerce_cart_session_initialize` returning false) keep older totals
		// in the session, which this calculation's currency doesn't describe. Without a stamp, the next load recalculates.
		// Totals the main cart saved during this calculation are stamped even if a later callback (e.g. store credit or a
		// fee plugin after priority 1000) changed its in-memory totals: they were calculated in this currency, and treating
		// them as stale would recalculate the cart on every load. They count only while the session still holds exactly
		// them, because another cart's session save fires the same action and can overwrite them.
		$saved_by_main_cart = null !== $this->main_cart_totals_saved_in_calculation && $saved_totals === $this->main_cart_totals_saved_in_calculation;
		if ( ! $saved_by_main_cart && $saved_totals !== $cart->get_totals() ) {
			$this->clear_cart_totals_currency();
			return;
		}

		WC()->session->set( self::CART_TOTALS_CURRENCY_SESSION_KEY, get_woocommerce_currency() );
		WC()->session->set( self::CART_TOTALS_HASH_SESSION_KEY, $this->get_cart_totals_hash( $saved_totals ) );
		WC()->session->__unset( self::STALE_TOTALS_RATES_DROPPED_SESSION_KEY );
	}

	/**
	 * Starts tracking whether the main cart's totals are saved during this calculation.
	 *
	 * @param \WC_Cart|null $cart The cart about to be calculated.
	 *
	 * @return void
	 */
	public function start_cart_totals_calculation( $cart = null ) {
		if ( $cart instanceof \WC_Cart && WC()->cart === $cart ) {
			$this->main_cart_in_calculation              = $cart;
			$this->main_cart_totals_saved_in_calculation = null;
		}
	}

	/**
	 * Notes the totals WC_Cart_Session::set_session() saved for the main cart during a cart calculation.
	 *
	 * `woocommerce_cart_updated` doesn't say which cart was saved, so a save counts as the main cart's only when the
	 * session now holds the main cart's totals.
	 *
	 * @return void
	 */
	public function note_cart_totals_saved() {
		if ( ! doing_action( 'woocommerce_after_calculate_totals' ) || null === $this->main_cart_in_calculation ) {
			return;
		}

		// WC_Cart_Session::set_session() fires this right after writing to the session, so the session exists.
		$saved_totals = WC()->session->get( 'cart_totals' );
		if ( $this->main_cart_in_calculation->get_totals() === $saved_totals ) {
			$this->main_cart_totals_saved_in_calculation = $saved_totals;
		}
	}

	/**
	 * Clears the cart totals currency stamp and totals hash: when the cart is emptied, so they don't keep an
	 * otherwise empty session alive, and whenever the saved totals are not the ones just calculated.
	 *
	 * @return void
	 */
	public function clear_cart_totals_currency() {
		if ( isset( WC()->session ) ) {
			WC()->session->__unset( self::CART_TOTALS_CURRENCY_SESSION_KEY );
			WC()->session->__unset( self::CART_TOTALS_HASH_SESSION_KEY );
		}
	}

	/**
	 * Returns a hash of saved cart totals, to tell whether they are the totals the currency stamp was recorded for.
	 *
	 * @param mixed $totals The cart totals.
	 *
	 * @return string
	 */
	private function get_cart_totals_hash( $totals ): string {
		return md5( (string) wp_json_encode( $totals ) );
	}

	/**
	 * Recalculates the loaded cart when its totals were calculated in a different currency than
	 * the one now in effect, so stale totals cannot be reused under the new currency.
	 *
	 * @return void
	 */
	public function maybe_recalculate_cart_for_currency() {
		// calculate_totals() returns before stamping an empty cart, so it would recalculate on every load. Removing the
		// last item doesn't empty_cart(), so drop the stamp here too.
		if ( WC()->cart->is_empty() ) {
			$this->clear_cart_totals_currency();
			WC()->session->__unset( self::STALE_TOTALS_RATES_DROPPED_SESSION_KEY );
			return;
		}

		// Without saved totals (a new session or a restored persistent cart), WooCommerce calculates them right after this hook.
		if ( null === WC()->session->get( 'cart_totals' ) ) {
			return;
		}

		// Nothing stamps or clears the totals on requests where Multi-Currency doesn't run, so totals saved there (e.g.
		// in the store currency while the merchant had it switched off) keep an older stamp. The hash tells them apart.
		if ( get_woocommerce_currency() === WC()->session->get( self::CART_TOTALS_CURRENCY_SESSION_KEY )
			&& $this->get_cart_totals_hash( WC()->session->get( 'cart_totals' ) ) === WC()->session->get( self::CART_TOTALS_HASH_SESSION_KEY ) ) {
			return;
		}

		// Shipping rates cached with stale totals can be stale too, without WooCommerce's package hash showing it: code
		// that doesn't mark packages with the currency (e.g. a release the store was rolled back to, or Multi-Currency
		// switched off) caches a rate in one currency that an unmarked store-currency package can match. Dropping them
		// costs no calculation here; the next one rebuilds them. They're dropped once per stale state: if something keeps
		// the stamp from being recorded, the rates rebuilt for the same totals stay cached instead of being requested from
		// live-rate carriers again on every calculation.
		$stale_state = $this->get_cart_totals_hash(
			[
				get_woocommerce_currency(),
				WC()->session->get( self::CART_TOTALS_CURRENCY_SESSION_KEY ),
				WC()->session->get( self::CART_TOTALS_HASH_SESSION_KEY ),
				WC()->session->get( 'cart_totals' ),
			]
		);
		if ( WC()->session->get( self::STALE_TOTALS_RATES_DROPPED_SESSION_KEY ) !== $stale_state ) {
			$this->clear_cached_shipping_rates();
			WC()->session->set( self::STALE_TOTALS_RATES_DROPPED_SESSION_KEY, $stale_state );
		}

		// Only REST requests can use saved totals without calculating them. Elsewhere a throwing third-party calculation
		// here would fail every page view, so only a request that selected the currency before the cart loaded recalculates.
		if ( ! $this->currency_changed_before_cart_load && ! $this->is_rest_request() ) {
			return;
		}

		$this->recalculate_cart();
	}

	/**
	 * Drops the shipping rates WooCommerce cached in the session for the cart's packages.
	 *
	 * @return void
	 */
	private function clear_cached_shipping_rates() {
		$session = WC()->session;
		if ( ! method_exists( $session, 'get_session_data' ) ) {
			return;
		}

		foreach ( array_keys( (array) $session->get_session_data() ) as $key ) {
			if ( 0 === strpos( (string) $key, 'shipping_for_package_' ) ) {
				$session->set( $key, null );
			}
		}
	}

	/**
	 * Whether the cart is loading for a REST request, including one whose cart loads before `REST_REQUEST` is defined.
	 *
	 * @return bool
	 */
	private function is_rest_request(): bool {
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! empty( $_REQUEST['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}

		return WC()->is_rest_api_request() || Utils::is_store_api_request();
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

		$order_exchange_rate = $order->get_meta( '_wcpay_multi_currency_order_exchange_rate', true );

		// Without the order rate, Multi-Currency did not convert the order, so neither the order nor the
		// refund is a Multi-Currency order. Copying empty values would still make Analytics treat the
		// refund as one and convert it with the Stripe rate, while the order stays unconverted.
		if ( ! $order_exchange_rate ) {
			return;
		}

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
		// A cached page is shared by visitors from every country, so it only carries a template for
		// the async renderer to fill in. A <template>'s content is inert, so WooCommerce's store
		// notice script doesn't find it and show it before the renderer knows the visitor's country.
		if ( $this->should_use_async_rendering() ) {
			// The markup is escaped as it's built.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<template id="wcpay-geolocation-notice">' . $this->get_geolocation_notice_template() . '</template>';
			return;
		}

		$current_currency = $this->get_selected_currency();

		// Don't run next checks if simulation is enabled.
		if ( ! $this->is_simulation_enabled() && ! $this->is_geolocated_currency_switch( $current_currency->get_code() ) ) {
			return;
		}

		$notice = $this->get_geolocation_notice( $current_currency );

		// The markup is escaped as it's built.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_geolocation_notice_markup( $notice['message'], $notice['id'] );
	}

	/**
	 * Gets the geolocation notice markup with empty slots for the country and currency names.
	 *
	 * @return string
	 */
	private function get_geolocation_notice_template(): string {
		$message = $this->get_geolocation_notice_message(
			'<span data-wcpay-notice-country></span>',
			'<span data-wcpay-notice-currency></span>'
		);

		return $this->get_geolocation_notice_markup( $message, '' );
	}

	/**
	 * Whether the visitor sees a currency other than the store's because of their location.
	 *
	 * @param string $currency_code The visitor's selected currency code.
	 *
	 * @return bool
	 */
	private function is_geolocated_currency_switch( string $currency_code ): bool {
		return get_option( 'woocommerce_currency' ) !== $currency_code
			&& $this->geolocation->get_currency_by_customer_location() === $currency_code;
	}

	/**
	 * Builds the geolocation notice for the visitor's country and the given currency.
	 *
	 * @param Currency $currency The currency the visitor was switched to.
	 *
	 * @return array{country_name: string, currency_name: string, message: string, id: string}
	 */
	private function get_geolocation_notice( Currency $currency ): array {
		$country = $this->geolocation->get_country_by_customer_location();

		// Cast the filtered names: a callback that forgets to return gives null, which the typed
		// helpers below reject.

		/**
		 * Filters the country name shown in the currency switch notice.
		 *
		 * @since 3.1.0
		 *
		 * @param string $country_name The geolocated country name.
		 */
		$country_name = (string) apply_filters( self::FILTER_PREFIX . 'override_notice_country', WC()->countries->countries[ $country ] );

		/**
		 * Filters the currency name shown in the currency switch notice.
		 *
		 * @since 3.1.0
		 *
		 * @param string $currency_name The current currency name.
		 */
		$currency_name = (string) apply_filters( self::FILTER_PREFIX . 'override_notice_currency_name', $currency->get_name() );

		$message = $this->get_geolocation_notice_message( $country_name, $currency_name );

		return [
			'country_name'  => $country_name,
			'currency_name' => $currency_name,
			'message'       => $message,
			// WooCommerce's store notice script names the dismissal cookie after this ID, so it
			// must stay the same wherever the notice renders for a dismissal to stick.
			'id'            => md5( $message ) . '2',
		];
	}

	/**
	 * Builds the geolocation notice sentence.
	 *
	 * @param string $country_name  The visitor's country name.
	 * @param string $currency_name The selected currency name.
	 *
	 * @return string
	 */
	private function get_geolocation_notice_message( string $country_name, string $currency_name ): string {
		$store_currency = get_option( 'woocommerce_currency' );
		$currencies     = get_woocommerce_currencies();

		return sprintf(
		/* translators: %1 User's country, %2 Selected currency name, %3 Default store currency name, %4 Link to switch currency */
			__( 'We noticed you\'re visiting from %1$s. We\'ve updated our prices to %2$s for your shopping convenience. <a href="%4$s">Use %3$s instead.</a>', 'woocommerce-payments' ),
			$country_name,
			$currency_name,
			esc_html( $currencies[ $store_currency ] ),
			esc_url( '?currency=' . $store_currency )
		);
	}

	/**
	 * Wraps the geolocation notice sentence in WooCommerce's store notice markup.
	 *
	 * @param string $message   The notice sentence.
	 * @param string $notice_id The notice ID.
	 *
	 * @return string
	 */
	private function get_geolocation_notice_markup( string $message, string $notice_id ): string {
		return '<p class="woocommerce-store-notice demo_store" data-notice-id="' . esc_attr( $notice_id ) . '" style="display:none;">'
			. $message
			. ' <a href="#" class="woocommerce-store-notice__dismiss-link">' . esc_html__( 'Dismiss', 'woocommerce-payments' ) . '</a></p>';
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
	public function add_woo_admin_notes() {
		// Do not try to add notes on ajax requests to improve their performance.
		if ( wp_doing_ajax() ) {
			return;
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '4.4.0', '>=' ) ) {
			NoteMultiCurrencyAvailable::set_account( $this->payments_account );
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
	 * Gets the rendering mode for multi-currency prices.
	 *
	 * @return string One of 'speed' or 'cache'.
	 */
	public function get_rendering_mode(): string {
		return get_option( self::RENDERING_MODE_OPTION, self::RENDERING_MODE_SPEED );
	}

	/**
	 * Checks if the cache-optimized rendering mode is active.
	 *
	 * @return bool
	 */
	public function is_cache_optimized_mode(): bool {
		return \WC_Payments_Features::is_mc_cache_optimized_enabled()
			&& self::RENDERING_MODE_CACHE === $this->get_rendering_mode();
	}

	/**
	 * Auto-enables the cache-optimized rendering mode, once, on sites that benefit from it.
	 *
	 * Runs at most once per site (guarded by CACHE_AUTODETECT_DONE_OPTION). It only switches the
	 * rendering mode to 'cache' when the merchant has never had a rendering mode written *and*
	 * a high-confidence caching environment is detected. An existing rendering mode value is never
	 * overridden — sites that have one fall back to the recommendation notice instead. See
	 * should_recommend_cache_mode().
	 *
	 * @return void
	 */
	public function maybe_auto_enable_cache_rendering_mode() {
		if ( 'yes' === get_option( self::CACHE_AUTODETECT_DONE_OPTION, 'no' ) ) {
			return;
		}

		// Don't mark detection as done while the feature is disabled, so it can run if enabled later.
		if ( ! \WC_Payments_Features::is_mc_cache_optimized_enabled() ) {
			return;
		}

		// Only auto-enable when the merchant has never had a rendering mode written. Any stored value
		// (including 'speed') means they have interacted with Store Settings, so we don't override it.
		if ( false === get_option( self::RENDERING_MODE_OPTION, false ) && $this->caching_environment->is_page_caching_active() ) {
			update_option( self::RENDERING_MODE_OPTION, self::RENDERING_MODE_CACHE );
		}

		update_option( self::CACHE_AUTODETECT_DONE_OPTION, 'yes' );
	}

	/**
	 * Checks if there is an active cookie-based WooCommerce session.
	 *
	 * Returns false for Store API requests that use Cart-Token JWT sessions,
	 * since WC's Store API SessionHandler does not implement has_session().
	 * Use Utils::is_store_api_request() to detect those separately.
	 *
	 * @return bool
	 */
	public function has_active_session(): bool {
		return isset( WC()->session )
			&& method_exists( WC()->session, 'has_session' )
			&& WC()->session->has_session();
	}

	/**
	 * Whether the async (client-side) price renderer should handle pricing
	 * instead of server-side FrontendPrices.
	 *
	 * Returns true only when all conditions are met:
	 * - Cache-optimized rendering mode is enabled.
	 * - No active WC session (cookie-based).
	 * - Not a Store API request (uses Cart-Token JWT sessions that bypass
	 *   has_active_session, but still needs server-side conversion).
	 *
	 * @return bool
	 */
	private function should_use_async_rendering(): bool {
		return $this->is_cache_optimized_mode()
			&& ! $this->has_active_session()
			&& ! Utils::is_store_api_request();
	}

	/**
	 * Whether to recommend switching to the cache-optimized rendering mode.
	 *
	 * True when the feature is available, the site is still on 'speed', a high-confidence caching
	 * environment is detected, and the merchant has not dismissed the recommendation. Drives the
	 * inline notice on the Store Settings page for sites that were not auto-enabled (i.e. they
	 * already had a rendering mode stored). See maybe_auto_enable_cache_rendering_mode().
	 *
	 * @return bool
	 */
	private function should_recommend_cache_mode(): bool {
		return \WC_Payments_Features::is_mc_cache_optimized_enabled()
			&& self::RENDERING_MODE_SPEED === $this->get_rendering_mode()
			&& 'yes' !== get_option( self::CACHE_RECOMMENDATION_DISMISSED_OPTION, 'no' )
			&& $this->caching_environment->is_page_caching_active();
	}

	/**
	 * Gets the public configuration data for the async price renderer.
	 *
	 * @return array The public config data including currencies, rates, and formatting.
	 */
	public function get_public_config(): array {
		$enabled_currencies = $this->get_enabled_currencies();
		$default_currency   = $this->get_default_currency();

		// Determine selected currency WITHOUT initializing a WC session.
		// This keeps the response cacheable and avoids setting session cookies.
		$selected_code = $default_currency->get_code();

		if ( $this->has_active_session() ) {
			// Session already exists (e.g. cart/checkout) — read from it safely.
			$stored = WC()->session->get( self::CURRENCY_SESSION_KEY );
			if ( $stored && isset( $enabled_currencies[ strtoupper( $stored ) ] ) ) {
				$selected_code = strtoupper( $stored );
			}
		} elseif ( $this->is_using_auto_currency_switching() ) {
			// Use geolocation to determine currency (does not create a session).
			$geo_currency = $this->geolocation->get_currency_by_customer_location();
			if ( $geo_currency && isset( $enabled_currencies[ $geo_currency ] ) ) {
				$selected_code = $geo_currency;
			}
		}

		$charm_only_products = $this->get_apply_charm_only_to_products();

		$currencies_data = [];
		$default_code    = $default_currency->get_code();
		foreach ( $enabled_currencies as $currency ) {
			$code = $currency->get_code();

			// For the default currency, use the merchant's WooCommerce store
			// settings (which they can customize) instead of the localization
			// service's hardcoded locale defaults. This ensures the JS-rendered
			// prices match what wc_price() produces server-side.
			if ( $code === $default_code ) {
				$decimals     = wc_get_price_decimals();
				$decimal_sep  = wc_get_price_decimal_separator();
				$thousand_sep = wc_get_price_thousand_separator();
				$symbol_pos   = get_option( 'woocommerce_currency_pos' );
			} else {
				$format       = $this->localization_service->get_currency_format( $code );
				$decimals     = absint( $format['num_decimals'] );
				$decimal_sep  = $format['decimal_sep'];
				$thousand_sep = $format['thousand_sep'];
				$symbol_pos   = $format['currency_pos'];
			}

			$currencies_data[ $code ] = [
				'code'         => $code,
				'symbol'       => get_woocommerce_currency_symbol( $code ),
				'rate'         => $currency->get_rate(),
				'decimals'     => $decimals,
				'decimal_sep'  => $decimal_sep,
				'thousand_sep' => $thousand_sep,
				'symbol_pos'   => $symbol_pos,
				'rounding'     => (float) $currency->get_rounding(),
				'charm'        => (float) $currency->get_charm(),
			];
		}

		$geolocation_notice = null;
		if ( $this->is_using_auto_currency_switching() && $this->is_geolocated_currency_switch( $selected_code ) ) {
			$notice             = $this->get_geolocation_notice( $enabled_currencies[ $selected_code ] );
			$geolocation_notice = [
				'country_name'  => \WC_Payments_Utils::decode_html_entities( $notice['country_name'] ),
				'currency_name' => \WC_Payments_Utils::decode_html_entities( $notice['currency_name'] ),
				'id'            => $notice['id'],
			];
		}

		return [
			'default_currency'    => $default_currency->get_code(),
			'selected_currency'   => $selected_code,
			'charm_only_products' => (bool) $charm_only_products,
			'currencies'          => $currencies_data,
			'geolocation_notice'  => $geolocation_notice,
		];
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
			'wcpay_multi_currency_rendering_mode'     => $this->get_rendering_mode(),
			'is_cache_optimized_feature_enabled'      => \WC_Payments_Features::is_mc_cache_optimized_enabled(),
			'should_recommend_cache_mode'             => $this->should_recommend_cache_mode(),
			'cache_recommendation_dismissed'          => 'yes' === get_option( self::CACHE_RECOMMENDATION_DISMISSED_OPTION, 'no' ),
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
			self::RENDERING_MODE_OPTION,
			self::CACHE_RECOMMENDATION_DISMISSED_OPTION,
		];

		foreach ( $updateable_options as $key ) {
			if ( ! isset( $params[ $key ] ) ) {
				continue;
			}

			$value = sanitize_text_field( $params[ $key ] );

			// Validate rendering mode to only accept known values.
			if ( self::RENDERING_MODE_OPTION === $key
				&& ! in_array( $value, [ self::RENDERING_MODE_SPEED, self::RENDERING_MODE_CACHE ], true ) ) {
				continue;
			}

			// Validate the cache recommendation dismissal flag to only accept known values.
			// This method can be called outside the REST route, which enforces the enum.
			if ( self::CACHE_RECOMMENDATION_DISMISSED_OPTION === $key
				&& ! in_array( $value, [ 'yes', 'no' ], true ) ) {
				continue;
			}

			update_option( $key, $value );
		}
	}

	/**
	 * Load script with all required dependencies.
	 *
	 * @param string $handler Script handler.
	 * @param string $script Script name relative to the plugin root.
	 * @param array  $additional_dependencies Additional dependencies.
	 *
	 * @return void
	 */
	public function register_script_with_dependencies( string $handler, string $script, array $additional_dependencies = [] ) {
		$script_file       = $script . '.js';
		$script_src_url    = plugins_url( $script_file, $this->settings_service->get_plugin_file_path() );
		$script_asset_path = plugin_dir_path( $this->settings_service->get_plugin_file_path() ) . $script . '.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require $script_asset_path : [ 'dependencies' => [] ]; // nosemgrep: audit.php.lang.security.file.inclusion-arg -- server generated path is used.
		$all_dependencies  = array_merge( $script_asset['dependencies'], $additional_dependencies );

		wp_register_script(
			$handler,
			$script_src_url,
			$all_dependencies,
			$this->get_file_version( $script_file ),
			true
		);
		wp_set_script_translations( $handler, 'woocommerce-payments' );
	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param string $file Local path to the file.
	 *
	 * @return string
	 */
	public function get_file_version( $file ) {
		$plugin_path = plugin_dir_path( $this->settings_service->get_plugin_file_path() );

		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $plugin_path . $file ) ) {
			return (string) filemtime( $plugin_path . trim( $file, '/' ) );
		}

		return $this->settings_service->get_plugin_version();
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
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return [];
		}

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
	 * Adjusts the given amount for the currently selected currency.
	 *
	 * Applies charm pricing if specified, and adjusts the amount according to
	 * the selected currency's conversion rate.
	 *
	 * @param  float $amount              The original amount to adjust.
	 * @param  bool  $apply_charm_pricing Optional. Whether to apply charm pricing to the adjusted amount. Default true.
	 * @return float                       The amount adjusted for the selected currency.
	 */
	public function adjust_amount_for_selected_currency( $amount, $apply_charm_pricing = true ) {
		return $this->get_adjusted_price( $amount, $apply_charm_pricing, $this->get_selected_currency() );
	}

	/**
	 * Returns the amount with the backend format.
	 *
	 * @param float $amount The amount to format.
	 * @param array $args The arguments to pass to wc_price.
	 *
	 * @return string The formatted amount.
	 */
	public function get_backend_formatted_wc_price( float $amount, array $args = [] ): string {
		return wc_price( $amount, $args );
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
		$rounding = (float) $currency->get_rounding();

		// If rounding is configured to be `0.00` we still need to round to the nearest lowest
		// currency denomination.
		// Otherwise we ceil the price to the configured rounding option.
		// NOTE: We don't round if currency rounding is > 0.00 because in those cases we want to
		// ceil the amount. For example: if $price = 1.251 and currency rounding = 0.25 we
		// want that amount ceiled to 1.50. If we round( 1.251 ) to 1.25 before ceiling the
		// price to the nearest 0.25 amount the final amount will be 1.25, which is incorrect.
		if ( 0.00 === $rounding ) {
			$num_decimals = absint(
				$this->localization_service->get_currency_format(
					$currency->get_code()
				)['num_decimals']
			);

			$price = round( $price, $num_decimals );
		} else {
			$price = $this->ceil_price( $price, $rounding );
		}

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
	 * Sets up the available currencies, which are alphabetical by name.
	 *
	 * @return void
	 */
	private function initialize_available_currencies() {
		// Add default store currency with a rate of 1.0.
		$woocommerce_currency                                = $this->get_store_currency_code();
		$this->available_currencies[ $woocommerce_currency ] = new Currency( $this->localization_service, $woocommerce_currency, 1.0 );

		$available_currencies = [];

		$currencies = $this->get_account_available_currencies();
		if ( ! empty( $currencies ) ) {
			$cache_data = $this->get_cached_currencies();

			foreach ( $currencies as $currency_code ) {
				$currency_rate = $cache_data['currencies'][ $currency_code ] ?? 1.0;
				$update_time   = $cache_data['updated'] ?? null;
				$new_currency  = new Currency( $this->localization_service, $currency_code, $currency_rate, $update_time );

				// Add this to our list of available currencies.
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
		$this->default_currency = $available_currencies[ $this->get_store_currency_code() ] ?? null;
	}

	/**
	 * Puts the module into its inert state: no currencies, no hooks, but every getter still
	 * returns a usable value.
	 *
	 * Used when init() must not proceed, so lazy-initializing getters and later init-hook
	 * callbacks neither re-trigger init() nor fatal. default_currency is assigned so
	 * get_default_currency() short-circuits. The frontend price and currency objects are
	 * constructed without their hooks, because get_frontend_prices() and
	 * get_frontend_currencies() have non-nullable return types.
	 *
	 * @return void
	 */
	private function set_inert_state() {
		$this->available_currencies = [];
		$this->enabled_currencies   = [];
		$this->default_currency     = new Currency( $this->localization_service, $this->get_store_currency_code() );
		$this->build_frontend_objects();
	}

	/**
	 * Switch to server price hooks without replacing their owning instances.
	 *
	 * @return void
	 */
	private function enable_server_price_hooks() {
		$this->async_renderer->remove_hooks();
		$this->frontend_currencies->selected_currency_changed();
		$this->frontend_prices->init_hooks();
		$this->frontend_currencies->init_hooks();
	}

	/**
	 * Constructs the frontend price and currency objects, without registering their hooks.
	 *
	 * Shared by the full initialization and the inert state so the two cannot drift. Objects that
	 * already exist are kept: callers may be holding a reference obtained through the getters.
	 *
	 * @return void
	 */
	private function build_frontend_objects() {
		$this->frontend_prices     = $this->frontend_prices ?? new FrontendPrices( $this, $this->compatibility );
		$this->frontend_currencies = $this->frontend_currencies ?? new FrontendCurrencies( $this, $this->localization_service, $this->utils, $this->compatibility );
	}

	/**
	 * Returns the configured WooCommerce store currency.
	 *
	 * This intentionally reads the raw option instead of get_woocommerce_currency().
	 * WooCommerce filters that helper for display and compatibility purposes, and
	 * visitor-facing currency switchers can use it to return the selected currency.
	 * Multi-Currency base-currency state must be tied to the admin setting instead.
	 * Keep cache invalidation and base-currency initialization on this helper to
	 * avoid reintroducing visitor-triggered cache churn.
	 *
	 * @return string
	 */
	private function get_store_currency_code(): string {
		return (string) get_option( 'woocommerce_currency', '' );
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

		// Reading the selection must never replace a session that is already in use.
		if ( ! isset( WC()->session ) ) {
			WC()->initialize_session();
		}
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
		$woocommerce_currency = $this->get_store_currency_code();

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
	 * Returns the currencies enabled for the payment provider account that are
	 * also available in WC.
	 *
	 * Can be filtered with the 'wcpay_multi_currency_available_currencies' hook.
	 *
	 * @return array Array with the available currencies' codes.
	 */
	private function get_account_available_currencies(): array {
		// If the payment provider is not connected, return an empty array. This prevents using MC without being connected to the payment provider.
		if ( ! $this->payments_account->is_provider_connected() ) {
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
	 * Register the CSS and JS admin scripts.
	 *
	 * @return void
	 */
	private function register_admin_scripts() {
		$this->register_script_with_dependencies( 'WCPAY_MULTI_CURRENCY_SETTINGS', 'dist/multi-currency', [ 'WCPAY_ADMIN_SETTINGS', 'wp-components' ] );

		wp_register_style(
			'WCPAY_MULTI_CURRENCY_SETTINGS',
			plugins_url( 'dist/multi-currency.css', $this->settings_service->get_plugin_file_path() ),
			[ 'wc-components', 'WCPAY_ADMIN_SETTINGS' ],
			$this->get_file_version( 'dist/multi-currency.css' ),
			'all'
		);
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

		$countries = $this->payments_account->get_supported_countries();

		$predefined_simulation_currencies = [
			'USD' => $countries['US'],
			'GBP' => $countries['GB'],
		];

		$simulation_currency      = 'USD' === get_option( 'woocommerce_currency', 'USD' ) ? 'GBP' : 'USD';
		$simulation_currency_name = $this->available_currencies[ $simulation_currency ]->get_name();
		$simulation_country       = $predefined_simulation_currencies[ $simulation_currency ];

		// Simulate client currency from geolocation.
		add_filter(
			'wcpay_multi_currency_override_notice_currency_name',
			function ( $_unused_selected_currency_name ) use ( $simulation_currency_name ) {
				return $simulation_currency_name;
			}
		);

		// Simulate client country from geolocation.
		add_filter(
			'wcpay_multi_currency_override_notice_country',
			function ( $_unused_selected_country ) use ( $simulation_country ) {
				return $simulation_country;
			}
		);

		// Always display the notice on simulation screen, prevent duplicate hooks.
		if ( ! has_action( 'wp_footer', [ $this, 'display_geolocation_currency_update_notice' ] ) ) {
			add_action( 'wp_footer', [ $this, 'display_geolocation_currency_update_notice' ] );
		}
	}

	/**
	 * Adds the required querystring parameters to all urls in preview pages.
	 *
	 * @return  void
	 */
	private function add_simulation_params_to_preview_urls() {
		$params = $this->simulation_params;
		add_action(
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
		throw new InvalidCurrencyException( esc_html( $message ), esc_html( $code ) );
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
}
