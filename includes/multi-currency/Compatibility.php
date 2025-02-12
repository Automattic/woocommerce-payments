<?php
/**
 * Class Compatibility
 *
 * @package WooCommerce\Payments\Compatibility
 */

namespace WCPay\MultiCurrency;

use WC_Order;
use WC_Order_Refund;
use WCPay\MultiCurrency\Compatibility\BaseCompatibility;
use WCPay\MultiCurrency\Compatibility\WooCommerceBookings;
use WCPay\MultiCurrency\Compatibility\WooCommerceFedEx;
use WCPay\MultiCurrency\Compatibility\WooCommerceNameYourPrice;
use WCPay\MultiCurrency\Compatibility\WooCommercePreOrders;
use WCPay\MultiCurrency\Compatibility\WooCommerceProductAddOns;
use WCPay\MultiCurrency\Compatibility\WooCommerceSubscriptions;
use WCPay\MultiCurrency\Compatibility\WooCommerceUPS;
use WCPay\MultiCurrency\Compatibility\WooCommerceDeposits;
use WCPay\MultiCurrency\Compatibility\WooCommercePointsAndRewards;

defined( 'ABSPATH' ) || exit;

/**
 * Class that controls Multi-Currency Compatibility.
 */
class Compatibility extends BaseCompatibility {

	/**
	 * Compatibility classes.
	 *
	 * @var array
	 */
	protected $compatibility_classes = [];

	/**
	 * Init the class.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'init_compatibility_classes' ], 11 );

		if ( defined( 'DOING_CRON' ) ) {
			add_filter( 'woocommerce_admin_sales_record_milestone_enabled', [ $this, 'attach_order_modifier' ] );
		}
	}

	/**
	 * Initializes our compatibility classes.
	 *
	 * @return void
	 */
	public function init_compatibility_classes() {
		if ( 1 < count( $this->multi_currency->get_enabled_currencies() ) ) {
			$this->compatibility_classes[] = new WooCommerceBookings( $this->multi_currency, $this->utils, $this->multi_currency->get_frontend_currencies() );
			$this->compatibility_classes[] = new WooCommerceFedEx( $this->multi_currency, $this->utils );
			$this->compatibility_classes[] = new WooCommerceNameYourPrice( $this->multi_currency, $this->utils );
			$this->compatibility_classes[] = new WooCommercePreOrders( $this->multi_currency, $this->utils );
			$this->compatibility_classes[] = new WooCommerceProductAddOns( $this->multi_currency, $this->utils );
			$this->compatibility_classes[] = new WooCommerceSubscriptions( $this->multi_currency, $this->utils );
			$this->compatibility_classes[] = new WooCommerceUPS( $this->multi_currency, $this->utils );
			$this->compatibility_classes[] = new WooCommerceDeposits( $this->multi_currency, $this->utils );
			$this->compatibility_classes[] = new WooCommercePointsAndRewards( $this->multi_currency, $this->utils );
		}
	}

	/**
	 * Returns the compatibility classes.
	 *
	 * @return array
	 */
	public function get_compatibility_classes(): array {
		return $this->compatibility_classes;
	}

	/**
	 * Checks to see if the selected currency needs to be overridden.
	 *
	 * @return mixed Three letter currency code or false if not.
	 */
	public function override_selected_currency() {
		return apply_filters( MultiCurrency::FILTER_PREFIX . 'override_selected_currency', false );
	}

	/**
	 * Deprecated method, please use should_disable_currency_switching.
	 *
	 * @return bool False if it shouldn't be hidden, true if it should.
	 */
	public function should_hide_widgets(): bool {
		wc_deprecated_function( __FUNCTION__, '6.5.0', 'Compatibility::should_disable_currency_switching' );
		return $this->should_disable_currency_switching();
	}

	/**
	 * Checks to see if currency switching should be disabled, such as the widgets and the automatic geolocation switching.
	 *
	 * @return bool False if no, true if yes.
	 */
	public function should_disable_currency_switching(): bool {
		$return = false;

		/**
		 * If the pay_for_order parameter is set, we disable currency switching.
		 *
		 * WooCommerce itself handles all the heavy lifting and verification on the Order Pay page, we just need to
		 * make sure the currency switchers are not displayed. This is due to once the order is created, the currency
		 * itself should remain static.
		 */
		if ( isset( $_GET['pay_for_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$return = true;
		}

		// If someone has hooked into the deprecated filter, throw a notice and then apply the filtering.
		if ( has_action( MultiCurrency::FILTER_PREFIX . 'should_hide_widgets' ) ) {
			wc_deprecated_hook( MultiCurrency::FILTER_PREFIX . 'should_hide_widgets', '6.5.0', MultiCurrency::FILTER_PREFIX . 'should_disable_currency_switching' );
			$return = apply_filters( MultiCurrency::FILTER_PREFIX . 'should_hide_widgets', $return );
		}

		return apply_filters( MultiCurrency::FILTER_PREFIX . 'should_disable_currency_switching', $return );
	}

	/**
	 * Checks to see if the coupon's amount should be converted.
	 *
	 * @param object $coupon Coupon object to test.
	 *
	 * @return bool True if it should be converted.
	 */
	public function should_convert_coupon_amount( $coupon = null ): bool {
		if ( ! $coupon ) {
			return true;
		}

		return apply_filters( MultiCurrency::FILTER_PREFIX . 'should_convert_coupon_amount', true, $coupon );
	}

	/**
	 * Checks to see if the product's price should be converted.
	 *
	 * @param object $product Product object to test.
	 *
	 * @return bool True if it should be converted.
	 */
	public function should_convert_product_price( $product = null ): bool {
		if ( ! $product ) {
			return true;
		}

		return apply_filters( MultiCurrency::FILTER_PREFIX . 'should_convert_product_price', true, $product );
	}

	/**
	 * Determines if the store currency should be returned or not.
	 *
	 * @return bool
	 */
	public function should_return_store_currency(): bool {
		return apply_filters( MultiCurrency::FILTER_PREFIX . 'should_return_store_currency', false );
	}

	/**
	 * This filter is called when the best sales day logic is called. We use it to add another filter which will
	 * convert the order prices used in this inbox notification.
	 *
	 * @param bool $arg Whether or not the best sales day logic should execute. We will just return this as is to
	 * respect the existing behaviour.
	 *
	 * @return bool
	 */
	public function attach_order_modifier( $arg ) {
		// Attach our filter to modify the order prices.
		add_filter( 'woocommerce_order_query', [ $this, 'convert_order_prices' ] );

		// This will be a bool value indication whether the best day logic should be run. Let's just return it as is.
		return $arg;
	}

	/**
	 * When a request is made by the "Best Sales Day" Inbox notification, we want to hook into this and convert
	 * the order totals to the store default currency.
	 *
	 * @param WC_Order[]|WC_Order_Refund[] $results The results returned by the orders query.
	 *
	 * @return array|object of WC_Order objects
	 */
	public function convert_order_prices( $results ) {
		$backtrace_calls = [
			'Automattic\WooCommerce\Admin\Notes\NewSalesRecord::sum_sales_for_date',
			'Automattic\WooCommerce\Admin\Notes\NewSalesRecord::possibly_add_note',
		];

		// If the results are not an array, or if the call we're expecting isn't in the backtrace, then just do nothing and return the results.
		if ( ! is_array( $results ) || ! $this->utils->is_call_in_backtrace( $backtrace_calls ) ) {
			return $results;
		}

		$default_currency = $this->multi_currency->get_default_currency();
		if ( ! $default_currency ) {
			return $results;
		}

		foreach ( $results as $order ) {
			if ( ! $order ||
				$order->get_currency() === $default_currency->get_code() ||
				! $order->get_meta( '_wcpay_multi_currency_order_exchange_rate', true ) ||
				$order->get_meta( '_wcpay_multi_currency_order_default_currency', true ) !== $default_currency->get_code()
			) {
				continue;
			}

			$exchange_rate = $order->get_meta( '_wcpay_multi_currency_order_exchange_rate', true );
			$order->set_total( number_format( $order->get_total() * ( 1 / $exchange_rate ), wc_get_price_decimals() ) );
		}

		remove_filter( 'woocommerce_order_query', [ $this, 'convert_order_prices' ] );

		return $results;
	}
}
