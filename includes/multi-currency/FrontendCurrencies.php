<?php
/**
 * WooCommerce Payments Multi-Currency Frontend Currencies
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\MultiCurrency;

use WC_Order;
use WCPay\MultiCurrency\Interfaces\MultiCurrencyLocalizationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Class that formats Multi-Currency currencies on the frontend.
 */
class FrontendCurrencies {
	/**
	 * How many innermost backtrace frames to scan when detecting an order being read from the data store.
	 *
	 * The WC_Abstract_Order::__construct() frame that drives the read sits a fixed, shallow distance below
	 * the wc_get_price_decimals filter (the intervening data-store read frames are internal and do not vary
	 * with the surrounding request), so a small cap detects it while keeping the per-call cost bounded.
	 *
	 * @var int
	 */
	private const ORDER_READ_BACKTRACE_DEPTH = 40;

	/**
	 * Multi-Currency instance.
	 *
	 * @var MultiCurrency
	 */
	protected $multi_currency;

	/**
	 * MultiCurrencyLocalizationInterface instance.
	 *
	 * @var MultiCurrencyLocalizationInterface
	 */
	protected $localization_service;

	/**
	 * Multi-currency utils instance.
	 *
	 * @var Utils
	 */
	protected $utils;

	/**
	 * Multi-currency compatibility instance.
	 *
	 * @var Compatibility
	 */
	protected $compatibility;

	/**
	 * Multi-Currency currency formatting map.
	 *
	 * @var array
	 */
	protected $currency_format = [];

	/**
	 * Order currency code.
	 *
	 * @var string|null
	 */
	protected $order_currency;

	/**
	 * WOO currency cache.
	 *
	 * @var string
	 */
	private $woocommerce_currency;

	/**
	 * Whether WooPayments has an explicit shopper or compatibility currency selection.
	 *
	 * @var bool|null
	 */
	private $has_explicit_currency_selection;

	/**
	 * Price Decimal Separator cache.
	 *
	 * @var array
	 */
	private $price_decimal_separators = [];

	/**
	 * Selected Currency Code cache.
	 *
	 * @var string
	 */
	private $selected_currency_code;

	/**
	 * Store Currency cache.
	 *
	 * @var Currency
	 */
	private $store_currency;

	/**
	 * Id of the user the selected currency was cached for.
	 *
	 * @var int|null
	 */
	private $cached_user_id;

	/**
	 * Session the selected currency was cached for.
	 *
	 * @var \WC_Session|null
	 */
	private $cached_session;

	/**
	 * Stack of order currencies for orders currently recalculating their totals (innermost last).
	 *
	 * A stack, not a single value, so a nested recalculation of another order does not drop the outer
	 * order's currency when the inner one finishes.
	 *
	 * @var array
	 */
	private $calculated_order_currencies = [];

	/**
	 * Whether init_order_currency() is reading the order it was given, so a call nested in that read
	 * returns early instead of reading again.
	 *
	 * @var bool
	 */
	private $initializing_order_currency = false;

	/**
	 * Whether an order may currently be loading from the data store.
	 *
	 * @var bool
	 */
	private $maybe_loading_order = false;

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency                      $multi_currency       The MultiCurrency instance.
	 * @param MultiCurrencyLocalizationInterface $localization_service The Localization Service instance.
	 * @param Utils                              $utils                Utils instance.
	 * @param Compatibility                      $compatibility        Compatibility instance.
	 */
	public function __construct( MultiCurrency $multi_currency, MultiCurrencyLocalizationInterface $localization_service, Utils $utils, Compatibility $compatibility ) {
		$this->multi_currency       = $multi_currency;
		$this->localization_service = $localization_service;
		$this->utils                = $utils;
		$this->compatibility        = $compatibility;
	}

	/**
	 * Initializes this class' WP hooks.
	 *
	 * @return void
	 */
	public function init_hooks() {
		if ( ! is_admin() && ! defined( 'DOING_CRON' ) && ! Utils::is_admin_api_request() ) {
			// Currency hooks.
			add_filter( 'woocommerce_currency', [ $this, 'get_woocommerce_currency' ], 900 );
			add_filter( 'wc_get_price_decimals', [ $this, 'get_price_decimals' ], 900 );
			add_filter( 'wc_get_price_decimal_separator', [ $this, 'get_price_decimal_separator' ], 900 );
			add_filter( 'wc_get_price_thousand_separator', [ $this, 'get_price_thousand_separator' ], 900 );
			add_filter( 'woocommerce_price_format', [ $this, 'get_woocommerce_price_format' ], 900 );
			add_filter( 'option_woocommerce_currency_pos', [ $this, 'get_woocommerce_currency_pos' ], 900 );
			add_action( 'before_woocommerce_pay', [ $this, 'init_order_currency_from_query_vars' ] );
			add_filter( 'woocommerce_order_get_total', [ $this, 'maybe_init_order_currency_from_order_total_prop' ], 900, 2 );
			add_filter( 'woocommerce_get_formatted_order_total', [ $this, 'maybe_clear_order_currency_after_formatted_order_total' ], 900, 4 );

			// A cart that changes under the same user and session can change the currency selection (e.g. subscription renewals).
			add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'selected_currency_changed' ], 0 );
			add_action( 'woocommerce_cart_updated', [ $this, 'selected_currency_changed' ], 0 );
			add_filter( 'woocommerce_order_class', [ $this, 'flag_order_loading' ], 900 );
			add_action( 'woocommerce_order_before_calculate_totals', [ $this, 'init_calculated_order_currency' ], 10, 2 );
			add_action( 'woocommerce_order_after_calculate_totals', [ $this, 'clear_calculated_order_currency' ], 900 );
		}

		add_filter( 'woocommerce_thankyou_order_id', [ $this, 'init_order_currency' ] );
		add_action( 'woocommerce_account_view-order_endpoint', [ $this, 'init_order_currency' ], 9 );
		add_filter( 'woocommerce_cart_hash', [ $this, 'add_currency_to_cart_hash' ], 900 );
		add_filter( 'woocommerce_shipping_method_add_rate_args', [ $this, 'fix_price_decimals_for_shipping_rates' ], 900, 2 );
	}

	/**
	 * The selected currency changed. We discard some cache.
	 *
	 * @return void
	 */
	public function selected_currency_changed() {
		$this->selected_currency_code          = null;
		$this->price_decimal_separators        = [];
		$this->woocommerce_currency            = null;
		$this->has_explicit_currency_selection = null;
		$this->store_currency                  = null;
	}

	/**
	 * Removes 'min_price' and 'max_price' from the URL query parameters.
	 *
	 * Clears existing price filters when the currency is changed to prevent inconsistencies
	 * during browser navigation. REST requests are excluded because redirecting a
	 * `/wp-json/wc/store/v1/products?currency=EUR&min_price=...` call (or the equivalent
	 * `/?rest_route=/wc/store/v1/products&...` form used when pretty permalinks are
	 * disabled) would strip the caller's filter bounds and break programmatic clients.
	 *
	 * Detection is URL-based rather than via the `REST_REQUEST` constant: this method is
	 * invoked from `MultiCurrency::update_selected_currency_by_url()` on `init:11`, but
	 * `REST_REQUEST` is only defined later on `parse_request` by `rest_api_loaded()` — a
	 * constant check here would always evaluate false on REST requests. Both URL-based
	 * checks (`WC()->is_rest_api_request()` and `$_GET['rest_route']`) work at any hook.
	 *
	 * @return void
	 */
	public function clear_url_price_params() {
		if (
			( function_exists( 'WC' ) && WC()->is_rest_api_request() )
			|| ! empty( $_GET['rest_route'] ) // phpcs:ignore WordPress.Security.NonceVerification
		) {
			return;
		}

		if ( isset( $_GET['min_price'] ) || isset( $_GET['max_price'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$url = remove_query_arg( [ 'min_price', 'max_price' ] );

			wp_safe_redirect( $url );
			exit;
		}
	}

	/**
	 * Gets the store currency.
	 *
	 * @return  Currency  The store currency wrapped as a Currency object
	 */
	public function get_store_currency(): Currency {
		if ( empty( $this->store_currency ) ) {
			$this->store_currency = $this->multi_currency->get_default_currency();
		}
		return $this->store_currency;
	}

	/**
	 * Returns the currency code to be used by WooCommerce.
	 *
	 * Runs on the `woocommerce_currency` filter at priority 900, after WooCommerce and any other
	 * plugin filtering the currency. Multi-Currency only asserts its own currency when the shopper
	 * or a compatibility rule has an explicit selection. When it is idle, it leaves the value
	 * produced by the earlier filters untouched, so a third-party currency switcher that hooks at a
	 * lower priority keeps working alongside an idle Multi-Currency module.
	 *
	 * The WordPress filter supplies its current currency as an undeclared argument.
	 * Reading it at runtime keeps the public zero-argument signature compatible with subclasses.
	 *
	 * @return string The code of the currency to be used.
	 */
	public function get_woocommerce_currency(): string {
		$currency = func_num_args() > 0 ? func_get_arg( 0 ) : '';

		if ( $this->compatibility->should_return_store_currency() ) {
			return $this->get_store_currency()->get_code();
		}

		$this->maybe_discard_cache_of_replaced_user_or_session();

		if ( empty( $this->woocommerce_currency ) ) {
			$explicit_currency                     = $this->multi_currency->get_explicit_selected_currency();
			$this->has_explicit_currency_selection = null !== $explicit_currency;
			$this->woocommerce_currency            = ( $explicit_currency ?? $this->get_store_currency() )->get_code();
		}

		if (
			false === $this->has_explicit_currency_selection
			&& is_string( $currency )
			&& '' !== $currency
		) {
			return $currency;
		}

		return $this->woocommerce_currency;
	}

	/**
	 * Returns the number of decimals to be used by WooCommerce.
	 *
	 * @param int $decimals The original decimal count.
	 *
	 * @return int The number of decimals.
	 */
	public function get_price_decimals( $decimals ): int {
		if ( $this->is_loading_order() ) {
			// While an order loads, only raise the decimals: the stored total is already rounded to the
			// order's own currency.
			$this->init_order_currency_from_query_vars();
			if ( null !== $this->order_currency ) {
				$decimals = max( $decimals, absint( $this->localization_service->get_currency_format( $this->order_currency )['num_decimals'] ) );
			}

			// A route without the id (e.g. the Store API) cannot name the order mid-read, so also take the
			// most decimals of any enabled currency.
			foreach ( $this->multi_currency->get_enabled_currencies() as $currency ) {
				$decimals = max( $decimals, absint( $this->localization_service->get_currency_format( $currency->get_code() )['num_decimals'] ) );
			}
			return $decimals;
		}

		$currency_code = $this->get_currency_code();
		if ( $currency_code !== $this->get_store_currency()->get_code() ) {
			return absint( $this->localization_service->get_currency_format( $currency_code )['num_decimals'] );
		}
		return $decimals;
	}

	/**
	 * Returns the decimal separator to be used by WooCommerce.
	 *
	 * @param string $separator The original separator.
	 *
	 * @return string The decimal separator.
	 */
	public function get_price_decimal_separator( $separator ): string {
		$currency_code       = $this->get_currency_code();
		$store_currency_code = $this->get_store_currency()->get_code();

		if ( $currency_code === $store_currency_code ) {
			$this->price_decimal_separators[ $currency_code ] = $separator;
		}

		if ( empty( $this->price_decimal_separators[ $currency_code ] ) ) {
			$this->price_decimal_separators[ $currency_code ] = $this->localization_service->get_currency_format( $currency_code )['decimal_sep'];
		}

		return $this->price_decimal_separators[ $currency_code ];
	}

	/**
	 * Returns the thousand separator to be used by WooCommerce.
	 *
	 * @param string $separator The original separator.
	 *
	 * @return string The thousand separator.
	 */
	public function get_price_thousand_separator( $separator ): string {
		$currency_code = $this->get_currency_code();
		if ( $currency_code !== $this->get_store_currency()->get_code() ) {
			return $this->localization_service->get_currency_format( $currency_code )['thousand_sep'];
		}
		return $separator;
	}

	/**
	 * Returns the currency format to be used by WooCommerce.
	 *
	 * @param string $format The original currency format.
	 *
	 * @return string The currency format.
	 */
	public function get_woocommerce_price_format( $format ): string {
		$currency_code = $this->get_currency_code();
		if ( $currency_code !== $this->get_store_currency()->get_code() ) {
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
		return $format;
	}

	/**
	 * Returns the currency symbol position for the currently selected currency.
	 *
	 * The Cart and Checkout Blocks read the `woocommerce_currency_pos` option (via the Store API
	 * CurrencyFormatter) rather than the `woocommerce_price_format` filter the shortcode uses. Without
	 * overriding the option, the Blocks fall back to the store's position setting and format non-store
	 * currencies (e.g. EUR) differently from the shortcode. Overriding it keeps both paths aligned to
	 * the currency's CLDR-defined position.
	 *
	 * @param string $position The original currency position option value.
	 *
	 * @return string The currency position for the selected currency.
	 */
	public function get_woocommerce_currency_pos( $position ): string {
		$currency_code = $this->get_currency_code();
		if ( $currency_code !== $this->get_store_currency()->get_code() ) {
			return $this->localization_service->get_currency_format( $currency_code )['currency_pos'];
		}
		return $position;
	}

	/**
	 * Adds the currency and exchange rate to the cart hash so it's recalculated properly.
	 *
	 * @param string $hash The cart hash.
	 *
	 * @return string The adjusted cart hash.
	 */
	public function add_currency_to_cart_hash( $hash ): string {
		$currency = $this->multi_currency->get_selected_currency();
		return md5( $hash . $currency->get_code() . $currency->get_rate() );
	}

	/**
	 * Inits order currency code.
	 *
	 * @param mixed $arg Either WC_Order or the id of an order are expected, but can be empty.
	 *
	 * @return int|mixed The order id or what was passed as $arg.
	 */
	public function init_order_currency( $arg ) {
		if ( null !== $this->order_currency || $this->initializing_order_currency ) {
			return $arg;
		}

		// 'wc_get_order' can trigger the formatting filters, and through them this method, so they are
		// removed for the read and the flag stops a nested call. The decimals filter stays: the read sets
		// the order's totals and WC_Order_Factory caches the order, so reading it at the store's decimals
		// would hand a rounded copy to every later wc_get_order() call in the request.
		remove_filter( 'woocommerce_price_format', [ $this, 'get_woocommerce_price_format' ], 900 );
		remove_filter( 'wc_get_price_thousand_separator', [ $this, 'get_price_thousand_separator' ], 900 );
		remove_filter( 'wc_get_price_decimal_separator', [ $this, 'get_price_decimal_separator' ], 900 );
		$this->initializing_order_currency = true;
		try {
			$order = ! $arg instanceof WC_Order ? wc_get_order( $arg ) : $arg;
		} finally {
			$this->initializing_order_currency = false;
			add_filter( 'wc_get_price_decimal_separator', [ $this, 'get_price_decimal_separator' ], 900 );
			add_filter( 'wc_get_price_thousand_separator', [ $this, 'get_price_thousand_separator' ], 900 );
			add_filter( 'woocommerce_price_format', [ $this, 'get_woocommerce_price_format' ], 900 );
		}

		if ( $order ) {
			$this->order_currency = $order->get_currency();
			return $order->get_id();
		}

		$this->order_currency = $this->multi_currency->get_selected_currency()->get_code();
		return $arg;
	}

	/**
	 * Gets the order id from the wp query_vars and then calls init_order_currency.
	 *
	 * @return void
	 */
	public function init_order_currency_from_query_vars() {
		$order_id = $this->get_order_id_from_query_vars();
		if ( null !== $order_id ) {
			$this->init_order_currency( $order_id );
		}
	}

	/**
	 * Returns the order id of a single-order page (order pay, order received or view order), if this is one.
	 *
	 * @return int|null The order id from the query vars, or null.
	 */
	private function get_order_id_from_query_vars() {
		global $wp;
		foreach ( [ 'order-pay', 'order-received', 'view-order' ] as $var ) {
			if ( ! empty( $wp->query_vars[ $var ] ) ) {
				$order_id = absint( $wp->query_vars[ $var ] );
				return 0 < $order_id ? $order_id : null;
			}
		}

		return null;
	}

	/**
	 * Fixes the decimals for the store currency when shipping rates are being determined.
	 * Our `wc_get_price_decimals` filter returns the decimals for the selected currency during this calculation, which leads to incorrect results.
	 *
	 * @param array  $args   The argument array to be filtered.
	 * @param object $_unused_method The shipping method being calculated.
	 *
	 * @return array
	 */
	public function fix_price_decimals_for_shipping_rates( array $args, $_unused_method ): array {
		$args['price_decimals'] = absint( $this->localization_service->get_currency_format( $this->get_store_currency()->get_code() )['num_decimals'] );
		return $args;
	}

	/**
	 * Returns the current value of order_currency.
	 *
	 * @return ?string The currency code or null.
	 */
	public function get_order_currency() {
		return $this->order_currency;
	}

	/**
	 * Maybe init the order_currency when the order total is queried if we should_use_order_currency.
	 *
	 * This works off of filtering during WC_Abstract_Order->get_total, which states it returns a float, however, in the instances of orders with negative
	 * amounts, such as refund orders, it will return a string.
	 *
	 * @param mixed    $total The order total.
	 * @param WC_Order $order The order being worked on.
	 *
	 * @return mixed The unmodified total.
	 */
	public function maybe_init_order_currency_from_order_total_prop( $total, $order ) {
		if ( $this->should_use_order_currency() ) {
			$this->init_order_currency( $order );
		}

		return $total;
	}

	/**
	 * If the order_currency is set and we should be using the order currency, clear it.
	 *
	 * This should only be happening on the instances tested for in should_use_order_currency. We would need to clear the order currency once the total
	 * filter is run so that if another total comes up, like in the order list, we use the next order's currency.
	 *
	 * @param string   $formatted_total  Total to display.
	 * @param WC_Order $_unused_order            Order data.
	 * @param string   $_unused_tax_display      Type of tax display.
	 * @param bool     $_unused_display_refunded If should include refunded value.
	 *
	 * @return string The unmodified formatted total.
	 */
	public function maybe_clear_order_currency_after_formatted_order_total( $formatted_total, $_unused_order, $_unused_tax_display, $_unused_display_refunded ): string {
		if ( null !== $this->order_currency && $this->should_use_order_currency() ) {
			$this->order_currency = null;
		}

		return $formatted_total;
	}

	/**
	 * Flags that an order may be loading from the data store.
	 *
	 * Runs on `woocommerce_order_class`, which `WC_Order_Factory` fires right before it reads an order.
	 * The flag is confirmed and cleared in is_loading_order().
	 *
	 * @param string $classname The order class name.
	 *
	 * @return string The unchanged order class name.
	 */
	public function flag_order_loading( $classname ) {
		$this->maybe_loading_order = true;
		return $classname;
	}

	/**
	 * Records the currency of the order whose totals are being recalculated.
	 *
	 * Runs on `woocommerce_order_before_calculate_totals` so amounts recalculated for an existing order
	 * follow the order's own currency rather than the shopper's selected currency.
	 *
	 * A calculate_totals() that throws before its after hook never pops its entry, so stale entries are
	 * reconciled here before recording this order: this calculate_totals() frame is already on the
	 * backtrace, so the valid existing depth is the live frame count minus one. That drops a stale entry
	 * left at the bottom of the stack by an unrelated failed calculation, which the read-time check in
	 * get_currency_code() would otherwise keep (it slices from the front), and makes this order the top
	 * entry.
	 *
	 * @param mixed $_unused_and_taxes Whether taxes are being calculated. Unused.
	 * @param mixed $order             The order recalculating its totals.
	 *
	 * @return void
	 */
	public function init_calculated_order_currency( $_unused_and_taxes, $order ) {
		$valid_existing = max( 0, $this->utils->get_backtrace_call_count( 'WC_Abstract_Order->calculate_totals' ) - 1 );
		if ( count( $this->calculated_order_currencies ) > $valid_existing ) {
			$this->calculated_order_currencies = array_slice( $this->calculated_order_currencies, 0, $valid_existing );
		}

		$this->calculated_order_currencies[] = ( $order instanceof WC_Order && $order->get_currency() ) ? $order->get_currency() : null;
	}

	/**
	 * Clears the recorded recalculating-order currency.
	 *
	 * @return void
	 */
	public function clear_calculated_order_currency() {
		array_pop( $this->calculated_order_currencies );
	}

	/**
	 * Gets the currency code for us to use.
	 *
	 * @return string|null Three letter currency code.
	 */
	private function get_currency_code() {
		if ( ! empty( $this->calculated_order_currencies ) ) {
			// Reconcile the stack against the live calculate_totals() frames and use the innermost order's
			// currency; see init_calculated_order_currency() for why entries can go stale.
			$active_calculations = $this->utils->get_backtrace_call_count( 'WC_Abstract_Order->calculate_totals' );
			if ( count( $this->calculated_order_currencies ) > $active_calculations ) {
				$this->calculated_order_currencies = array_slice( $this->calculated_order_currencies, 0, $active_calculations );
			}
			$order_currency = end( $this->calculated_order_currencies );
			if ( false !== $order_currency && null !== $order_currency ) {
				return $order_currency;
			}
		}

		if ( $this->should_use_order_currency() ) {
			$this->init_order_currency_from_query_vars();

			return $this->order_currency;
		}

		$this->selected_currency_code = $this->get_selected_currency_code();

		return $this->selected_currency_code;
	}

	/**
	 * Helper function to "cache" the selected currency.
	 *
	 * @return string
	 */
	private function get_selected_currency_code(): string {
		$this->maybe_discard_cache_of_replaced_user_or_session();

		if ( empty( $this->selected_currency_code ) ) {
			$this->selected_currency_code = $this->multi_currency->get_selected_currency()->get_code();
		}
		return $this->selected_currency_code;
	}

	/**
	 * Discards the cache if the user or the session the selected currency is stored for was replaced
	 * during the request, e.g. by `wp_set_current_user()` or by a Store API request with a Cart-Token.
	 *
	 * Prices are always converted with the selection of the current user or session, so the cached
	 * codes have to follow it. Otherwise amounts end up labelled with a currency they are not in.
	 *
	 * @return void
	 */
	private function maybe_discard_cache_of_replaced_user_or_session() {
		$user_id = get_current_user_id();
		$session = WC()->session;

		if ( $user_id !== $this->cached_user_id || $session !== $this->cached_session ) {
			$this->selected_currency_changed();
			$this->cached_user_id = $user_id;
			$this->cached_session = $session;
		}
	}

	/**
	 * Checks whether currency code used for formatting should be overridden.
	 *
	 * @return bool
	 */
	private function should_use_order_currency(): bool {
		$pages = [ 'my-account', 'checkout' ];
		$vars  = [ 'order-received', 'order-pay', 'orders', 'view-order' ];

		if ( ! $this->utils->is_page_with_vars( $pages, $vars ) ) {
			return false;
		}

		$calls = [
			'WC_Shortcode_My_Account::view_order',
			'WC_Shortcode_Checkout::order_received',
			'WC_Shortcode_Checkout::order_pay',
			'WC_Order->get_formatted_order_total',
		];

		// Block-based confirmation pages render the order total without the shortcode classes above;
		// loads are handled separately (is_loading_order()). Other prices here keep the selected currency.
		if ( null !== $this->get_order_id_from_query_vars() ) {
			$calls[] = 'Automattic\WooCommerce\Blocks\BlockTypes\OrderConfirmation\AbstractOrderConfirmationBlock->render';
		}

		return $this->utils->is_call_in_backtrace( $calls );
	}

	/**
	 * Returns whether an order is currently being read from the data store.
	 *
	 * A flag is set by flag_order_loading() right before the read. No hook marks the end of the read,
	 * so the backtrace is consulted here and the flag cleared once the factory is no longer loading.
	 *
	 * @return bool
	 */
	private function is_loading_order(): bool {
		if ( $this->maybe_loading_order ) {
			$this->maybe_loading_order = $this->utils->is_call_in_backtrace( [ 'WC_Order_Factory::get_order', 'WC_Order_Factory::get_orders' ] );
			if ( $this->maybe_loading_order ) {
				return true;
			}
		}

		// An order held in a persistent object cache is re-read when it is unserialized: WC_Data::__wakeup()
		// calls WC_Abstract_Order::__construct(), which skips WC_Order_Factory and so flag_order_loading().
		// Without this, the order would load at the selected currency's decimals. Match the order
		// constructor, not WC_Data::__wakeup(), which products and other data objects share.
		return $this->utils->is_call_in_backtrace_within( [ 'WC_Abstract_Order->__construct' ], self::ORDER_READ_BACKTRACE_DEPTH );
	}
}
