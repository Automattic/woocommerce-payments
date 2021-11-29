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
	protected $order_currency = null;

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

		if ( ! is_admin() && ! defined( 'DOING_CRON' ) && ! Utils::is_admin_api_request() ) {
			// Currency hooks.
			add_filter( 'woocommerce_currency', [ $this, 'get_woocommerce_currency' ], 50 );
			add_filter( 'wc_get_price_decimals', [ $this, 'get_price_decimals' ], 50 );
			add_filter( 'wc_get_price_decimal_separator', [ $this, 'get_price_decimal_separator' ], 50 );
			add_filter( 'wc_get_price_thousand_separator', [ $this, 'get_price_thousand_separator' ], 50 );
			add_filter( 'woocommerce_price_format', [ $this, 'get_woocommerce_price_format' ], 50 );
			add_action( 'before_woocommerce_pay', [ $this, 'init_order_currency_from_query_vars' ] );
		}

		add_filter( 'woocommerce_thankyou_order_id', [ $this, 'init_order_currency' ] );
		add_action( 'woocommerce_account_view-order_endpoint', [ $this, 'init_order_currency' ], 9 );
		add_filter( 'woocommerce_cart_hash', [ $this, 'add_currency_to_cart_hash' ], 50 );
		add_filter( 'woocommerce_shipping_method_add_rate_args', [ $this, 'fix_price_decimals_for_shipping_rates' ], 50, 2 );
	}

	/**
	 * Gets the store currency.
	 *
	 * @return  Currency  The store currency wrapped as a Currency object
	 */
	public function get_store_currency() {
		return new Currency( get_option( 'woocommerce_currency' ) );
	}

	/**
	 * Returns the currency code to be used by WooCommerce.
	 *
	 * @return string The code of the currency to be used.
	 */
	public function get_woocommerce_currency(): string {
		if ( $this->compatibility->should_return_store_currency() ) {
			return $this->multi_currency->get_default_currency()->get_code();
		}
		return $this->multi_currency->get_selected_currency()->get_code();
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
		$currency_code = $this->get_currency_code();
		if ( $currency_code !== $this->get_store_currency()->get_code() ) {
			return $this->localization_service->get_currency_format( $currency_code )['decimal_sep'];
		}
		return $separator;
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
	 * @return int The order id or what was passed as $arg.
	 */
	public function init_order_currency( $arg ) {
		if ( null !== $this->order_currency ) {
			return $arg;
		}

		$order = ! $arg instanceof WC_Order ? wc_get_order( $arg ) : $arg;

		if ( $order ) {
			$this->order_currency = $order->get_currency();
			return $order->get_id();
		}

		$this->order_currency = $this->multi_currency->get_selected_currency();
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
	 * Gets the currency code for us to use.
	 *
	 * @return string|null Three letter currency code.
	 */
	private function get_currency_code() {
		if ( $this->should_override_currency_code() ) {
			return $this->order_currency;
		}
		return $this->multi_currency->get_selected_currency()->get_code();
	}

	/**
	 * Checks whether currency code used for formatting should be overridden.
	 *
	 * @return bool
	 */
	private function should_override_currency_code(): bool {
		return $this->utils->is_call_in_backtrace(
			[
				'WC_Shortcode_My_Account::view_order',
				'WC_Shortcode_Checkout::order_received',
				'WC_Shortcode_Checkout::order_pay',
			]
		);
	}
}
