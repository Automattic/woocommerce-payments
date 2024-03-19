<?php
/**
 * WooCommerce Payments Multi-Currency Frontend Currencies
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\MultiCurrency;

use WC_Order;
use WC_Payments_Localization_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Class that formats Multi-Currency currencies on the frontend.
 */
class FrontendCurrencies {
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
	 * Constructor.
	 *
	 * @param MultiCurrency                    $multi_currency       The MultiCurrency instance.
	 * @param WC_Payments_Localization_Service $localization_service The Localization Service instance.
	 * @param Utils                            $utils                Utils instance.
	 * @param Compatibility                    $compatibility        Compatibility instance.
	 */
	public function __construct( MultiCurrency $multi_currency, WC_Payments_Localization_Service $localization_service, Utils $utils, Compatibility $compatibility ) {
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
			add_filter( 'wc_get_price_thousand_separator', [ $this, 'get_price_thousand_separator' ], 900 );
			add_filter( 'woocommerce_price_format', [ $this, 'get_woocommerce_price_format' ], 900 );
			add_action( 'before_woocommerce_pay', [ $this, 'init_order_currency_from_query_vars' ] );
			add_action( 'woocommerce_order_get_total', [ $this, 'maybe_init_order_currency_from_order_total_prop' ], 900, 2 );
			add_action( 'woocommerce_get_formatted_order_total', [ $this, 'maybe_clear_order_currency_after_formatted_order_total' ], 900, 4 );

			// Note: it's important that 'init_order_currency_from_query_vars' is called before
			// 'get_price_decimal_separator' because the order currency is often required to
			// determine the decimal separator. That's why the priority on 'init_order_currency_from_query_vars'
			// is explicity lower than the priority of 'get_price_decimal_separator'.
			add_filter( 'wc_get_price_decimal_separator', [ $this, 'init_order_currency_from_query_vars' ], 900 );
			add_filter( 'wc_get_price_decimal_separator', [ $this, 'get_price_decimal_separator' ], 901 );
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
		$this->selected_currency_code   = null;
		$this->price_decimal_separators = [];
		$this->woocommerce_currency     = null;
		$this->store_currency           = null;
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
	 * @return string The code of the currency to be used.
	 */
	public function get_woocommerce_currency(): string {
		if ( $this->compatibility->should_return_store_currency() ) {
			return $this->get_store_currency()->get_code();
		}

		if ( empty( $this->woocommerce_currency ) ) {
			$this->woocommerce_currency = $this->get_selected_currency_code();
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
			$currency_code                                    = $store_currency_code;
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
		if ( null !== $this->order_currency ) {
			return $arg;
		}

		// We remove the filters here becuase 'wc_get_order' triggers the 'wc_get_price_decimal_separator' filter.
		remove_filter( 'wc_get_price_decimal_separator', [ $this, 'get_price_decimal_separator' ], 901 );
		remove_filter( 'wc_get_price_decimal_separator', [ $this, 'init_order_currency_from_query_vars' ], 900 );
		$order = ! $arg instanceof WC_Order ? wc_get_order( $arg ) : $arg;
		// Note: it's important that 'init_order_currency_from_query_vars' is called before
		// 'get_price_decimal_separator' because the order currency is often required to
		// determine the decimal separator. That's why the priority on 'init_order_currency_from_query_vars'
		// is explicity lower than the priority of 'get_price_decimal_separator'.
		add_filter( 'wc_get_price_decimal_separator', [ $this, 'init_order_currency_from_query_vars' ], 900 );
		add_filter( 'wc_get_price_decimal_separator', [ $this, 'get_price_decimal_separator' ], 901 );

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
		global $wp;
		if ( ! empty( $wp->query_vars['order-pay'] ) ) {
			$this->init_order_currency( $wp->query_vars['order-pay'] );
		} elseif ( ! empty( $wp->query_vars['order-received'] ) ) {
			$this->init_order_currency( $wp->query_vars['order-received'] );
		} elseif ( ! empty( $wp->query_vars['view-order'] ) ) {
			$this->init_order_currency( $wp->query_vars['view-order'] );
		}
	}

	/**
	 * Fixes the decimals for the store currency when shipping rates are being determined.
	 * Our `wc_get_price_decimals` filter returns the decimals for the selected currency during this calculation, which leads to incorrect results.
	 *
	 * @param array  $args   The argument array to be filtered.
	 * @param object $method The shipping method being calculated.
	 *
	 * @return array
	 */
	public function fix_price_decimals_for_shipping_rates( array $args, $method ): array {
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
	 * @param WC_Order $order            Order data.
	 * @param string   $tax_display      Type of tax display.
	 * @param bool     $display_refunded If should include refunded value.
	 *
	 * @return string The unmodified formatted total.
	 */
	public function maybe_clear_order_currency_after_formatted_order_total( $formatted_total, $order, $tax_display, $display_refunded ): string {
		if ( null !== $this->order_currency && $this->should_use_order_currency() ) {
			$this->order_currency = null;
		}

		return $formatted_total;
	}

	/**
	 * Gets the currency code for us to use.
	 *
	 * @return string|null Three letter currency code.
	 */
	private function get_currency_code() {
		if ( $this->should_use_order_currency() ) {
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
		if ( empty( $this->selected_currency_code ) ) {
			$this->selected_currency_code = $this->multi_currency->get_selected_currency()->get_code();
		}
		return $this->selected_currency_code;
	}

	/**
	 * Checks whether currency code used for formatting should be overridden.
	 *
	 * @return bool
	 */
	private function should_use_order_currency(): bool {
		$pages = [ 'my-account', 'checkout' ];
		$vars  = [ 'order-received', 'order-pay', 'order-received', 'orders', 'view-order' ];

		if ( $this->utils->is_page_with_vars( $pages, $vars ) ) {
			return $this->utils->is_call_in_backtrace(
				[
					'WC_Shortcode_My_Account::view_order',
					'WC_Shortcode_Checkout::order_received',
					'WC_Shortcode_Checkout::order_pay',
					'WC_Order->get_formatted_order_total',
				]
			);
		}

		return false;
	}
}
