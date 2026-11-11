<?php
/**
 * WooCommerce Payments Multi-Currency Frontend Currencies
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\MultiCurrency;

use WCPay\MultiCurrency\Interfaces\MultiCurrencyLocalizationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Class that formats Multi-Currency currencies on the frontend.
 *
 * WooCommerce passes the active currency to its formatting filters, so the formatting follows the currency being
 * worked on (e.g. an order's), and the shopper's selected currency is used otherwise.
 */
class FrontendCurrencies {
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
	 * Price Decimal Separator cache, keyed by currency code.
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
		if ( Utils::is_frontend_request() ) {
			add_filter( 'woocommerce_currency', [ $this, 'get_woocommerce_currency' ], 900 );
			add_filter( 'wc_get_price_decimals', [ $this, 'get_price_decimals' ], 900, 2 );
			add_filter( 'wc_get_price_decimal_separator', [ $this, 'get_price_decimal_separator' ], 900, 2 );
			add_filter( 'wc_get_price_thousand_separator', [ $this, 'get_price_thousand_separator' ], 900, 2 );
			add_filter( 'woocommerce_price_format', [ $this, 'get_woocommerce_price_format' ], 900, 3 );
			add_filter( 'wc_get_currency_position', [ $this, 'get_price_position' ], 900, 2 );
			// Kept on this path too: the Cart and Checkout Blocks read the option without passing a currency.
			add_filter( 'option_woocommerce_currency_pos', [ $this, 'get_woocommerce_currency_pos' ], 900 );

			// A cart that changes under the same user and session can change the currency selection (e.g. subscription renewals).
			add_action( 'woocommerce_cart_loaded_from_session', [ $this, 'selected_currency_changed' ], 0 );
			add_action( 'woocommerce_cart_updated', [ $this, 'selected_currency_changed' ], 0 );
		}

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
	 * during browser navigation. It redirects, so it must only run for frontend page loads once the
	 * query is parsed: MultiCurrency::update_selected_currency() schedules it on `template_redirect`,
	 * which never fires for REST, AJAX or cron requests, where stripping a caller's
	 * `min_price`/`max_price` would break programmatic clients.
	 *
	 * @return void
	 */
	public function clear_url_price_params() {
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
	 * @param int         $decimals The original decimal count.
	 * @param string|null $currency The active currency WooCommerce formats for, when it sets one.
	 *
	 * @return int The number of decimals.
	 */
	public function get_price_decimals( $decimals, $currency = null ): int {
		$currency_code = $this->get_currency_code( $currency );
		if ( $currency_code !== $this->get_store_currency()->get_code() ) {
			return absint( $this->localization_service->get_currency_format( $currency_code )['num_decimals'] );
		}
		return $decimals;
	}

	/**
	 * Returns the decimal separator to be used by WooCommerce.
	 *
	 * @param string      $separator The original separator.
	 * @param string|null $currency  The active currency WooCommerce formats for, when it sets one.
	 *
	 * @return string The decimal separator.
	 */
	public function get_price_decimal_separator( $separator, $currency = null ): string {
		$currency_code       = $this->get_currency_code( $currency );
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
	 * @param string      $separator The original separator.
	 * @param string|null $currency  The active currency WooCommerce formats for, when it sets one.
	 *
	 * @return string The thousand separator.
	 */
	public function get_price_thousand_separator( $separator, $currency = null ): string {
		$currency_code = $this->get_currency_code( $currency );
		if ( $currency_code !== $this->get_store_currency()->get_code() ) {
			return $this->localization_service->get_currency_format( $currency_code )['thousand_sep'];
		}
		return $separator;
	}

	/**
	 * Returns the currency format to be used by WooCommerce.
	 *
	 * @param string      $format               The original currency format.
	 * @param string|null $_unused_currency_pos The currency position WooCommerce passes alongside the format. Unused.
	 * @param string|null $currency             The active currency WooCommerce formats for, when it sets one.
	 *
	 * @return string The currency format.
	 */
	public function get_woocommerce_price_format( $format, $_unused_currency_pos = null, $currency = null ): string {
		$currency_code = $this->get_currency_code( $currency );
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
	 * Returns the currency symbol position for the active currency, as the `wc_get_currency_position` filter passes it.
	 *
	 * @param string      $position The original currency position.
	 * @param string|null $currency The active currency WooCommerce formats for, when it sets one.
	 *
	 * @return string The currency position.
	 */
	public function get_price_position( $position, $currency = null ) {
		$currency_code = $this->get_currency_code( $currency );
		if ( $currency_code !== $this->get_store_currency()->get_code() ) {
			return $this->localization_service->get_currency_format( $currency_code )['currency_pos'];
		}
		return $position;
	}

	/**
	 * Returns the currency symbol position option for the shopper's currency.
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
		return $this->get_price_position( $position );
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
	 * Gets the currency code to format with: the active currency WooCommerce passes when it sets one,
	 * otherwise the shopper's selected currency.
	 *
	 * @param string|null $currency The active currency code, when WooCommerce passes one.
	 *
	 * @return string Three letter currency code.
	 */
	private function get_currency_code( $currency = null ): string {
		if ( is_string( $currency ) && '' !== $currency ) {
			return $currency;
		}

		return $this->get_selected_currency_code();
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
}
