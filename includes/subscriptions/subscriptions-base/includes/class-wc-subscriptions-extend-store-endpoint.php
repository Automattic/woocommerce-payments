<?php
/**
 * WooCommerce Subscriptions Extend Store API.
 *
 * A class to extend the store public API with subscription related data
 * for each subscription item
 *
 * @package WooCommerce Subscriptions
 * @since   WCBLOCKS-DEV
 */

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\ExtendRestApi;
use Automattic\WooCommerce\Blocks\StoreApi\SchemaController;
use Automattic\WooCommerce\Blocks\StoreApi\Schemas\CartSchema;
use Automattic\WooCommerce\Blocks\StoreApi\Schemas\CartItemSchema;

class WC_Subscriptions_Extend_Store_Endpoint {

	/**
	 * Stores Rest Schema Controller.
	 *
	 * @var ExtendRestApi
	 */
	private static $schema;

	/**
	 * Stores Rest Extending instance.
	 *
	 * @var ExtendRestApi
	 */
	private static $extend;

	/**
	 * Plugin Identifier, unique to each plugin.
	 *
	 * @var string
	 */
	const IDENTIFIER = 'subscriptions';

	/**
	 * Bootstraps the class and hooks required data.
	 *
	 * @since WCBLOCKS-DEV
	 */
	public static function init() {
		self::$schema = Package::container()->get( SchemaController::class );
		self::$extend = Package::container()->get( ExtendRestApi::class );
		self::extend_store();
	}

	/**
	 * Registers the actual data into each endpoint.
	 */
	public static function extend_store() {

		// Register into `cart/items`
		self::$extend->register_endpoint_data(
			array(
				'endpoint'        => CartItemSchema::IDENTIFIER,
				'namespace'       => self::IDENTIFIER,
				'data_callback'   => array( 'WC_Subscriptions_Extend_Store_Endpoint', 'extend_cart_item_data' ),
				'schema_callback' => array( 'WC_Subscriptions_Extend_Store_Endpoint', 'extend_cart_item_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);

		// Register into `cart`
		self::$extend->register_endpoint_data(
			array(
				'endpoint'        => CartSchema::IDENTIFIER,
				'namespace'       => self::IDENTIFIER,
				'data_callback'   => array( 'WC_Subscriptions_Extend_Store_Endpoint', 'extend_cart_data' ),
				'schema_callback' => array( 'WC_Subscriptions_Extend_Store_Endpoint', 'extend_cart_schema' ),
				'schema_type'     => ARRAY_N,
			)
		);

		// Register payment requirements.
		self::$extend->register_payment_requirements(
			array(
				'data_callback' => array( WC_Subscriptions_Base_Plugin::instance()->get_gateways_handler_class(), 'inject_payment_feature_requirements_for_cart_api' ),
			)
		);
	}

	/**
	 * Register subscription product data into cart/items endpoint.
	 *
	 * @param array $cart_item Current cart item data.
	 *
	 * @return array $item_data Registered data or empty array if condition is not satisfied.
	 */
	public static function extend_cart_item_data( $cart_item ) {
		$product   = $cart_item['data'];
		$item_data = array(
			'billing_period'      => null,
			'billing_interval'    => null,
			'subscription_length' => null,
			'trial_length'        => null,
			'trial_period'        => null,
			'sign_up_fees'        => null,
			'sign_up_fees_tax'    => null,
			'is_resubscribe'      => null,
			'switch_type'         => null,
			'synchronization'     => null,

		);

		if ( in_array( $product->get_type(), array( 'subscription', 'subscription_variation' ), true ) ) {
			$item_data = array_merge(
				array(
					'billing_period'      => WC_Subscriptions_Product::get_period( $product ),
					'billing_interval'    => (int) WC_Subscriptions_Product::get_interval( $product ),
					'subscription_length' => (int) WC_Subscriptions_Product::get_length( $product ),
					'trial_length'        => (int) WC_Subscriptions_Product::get_trial_length( $product ),
					'trial_period'        => WC_Subscriptions_Product::get_trial_period( $product ),
					'is_resubscribe'      => isset( $cart_item['subscription_resubscribe'] ),
					'switch_type'         => wcs_get_cart_item_switch_type( $cart_item ),
					'synchronization'     => self::format_sync_data( $product ),
				),
				self::format_sign_up_fees( $product )
			);
		}

		return $item_data;
	}

	/**
	 * Register subscription product schema into cart/items endpoint.
	 *
	 * @return array Registered schema.
	 */
	public static function extend_cart_item_schema() {
		return array(
			'billing_period'      => array(
				'description' => __( 'Billing period for the subscription.', 'woocommerce-subscriptions' ),
				'type'        => array( 'string', 'null' ),
				'enum'        => array_keys( wcs_get_subscription_period_strings() ),
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'billing_interval'    => array(
				'description' => __( 'The number of billing periods between subscription renewals.', 'woocommerce-subscriptions' ),
				'type'        => array( 'integer', 'null' ),
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'subscription_length' => array(
				'description' => __( 'Subscription Product length.', 'woocommerce-subscriptions' ),
				'type'        => array( 'integer', 'null' ),
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'trial_period'        => array(
				'description' => __( 'Subscription Product trial period.', 'woocommerce-subscriptions' ),
				'type'        => array( 'string', 'null' ),
				'enum'        => array_keys( wcs_get_subscription_period_strings() ),
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'trial_length'        => array(
				'description' => __( 'Subscription Product trial interval.', 'woocommerce-subscriptions' ),
				'type'        => array( 'integer', 'null' ),
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'sign_up_fees'        => array(
				'description' => __( 'Subscription Product signup fees.', 'woocommerce-subscriptions' ),
				'type'        => array( 'string', 'null' ),
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'sign_up_fees_tax'    => array(
				'description' => __( 'Subscription Product signup fees taxes.', 'woocommerce-subscriptions' ),
				'type'        => array( 'string', 'null' ),
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'is_resubscribe'      => array(
				'description' => __(
					'Indicates whether this product is being used to resubscribe the customer to an existing, expired subscription.',
					'woocommerce-subscriptions'
				),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'switch_type'         => array(
				'description' => __(
					'Indicates whether this product a subscription update, downgrade, cross grade or none of the above.',
					'woocommerce-subscriptions'
				),
				'type'        => array( 'string', 'null' ),
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'synchronization'     => array(
				'description' => __( 'Synchronization data for the subscription.', 'woocommerce-subscriptions' ),
				'type'        => array( 'object', 'integer', 'null' ),
				'properties'  => array(
					'day'   => array(
						'description' => __( 'Synchronization day if subscription is annual.', 'woocommerce-subscriptions' ),
						'type'        => 'integer',
					),
					'month' => array(
						'description' => __( 'Synchronization month if subscription is annual.', 'woocommerce-subscriptions' ),
						'type'        => 'integer',
					),
				),
			),
		);
	}

	/**
	 * Get packages from the recurring carts.
	 *
	 * @param string $cart_key Recurring cart key.
	 * @param array  $cart Recurring cart data.
	 * @return array
	 */
	private static function get_packages_for_recurring_cart( $cart_key, $cart ) {
		$packages_by_cart = WC_Subscriptions_Cart::get_recurring_shipping_packages();

		if ( ! isset( $packages_by_cart[ $cart_key ] ) ) {
			return array();
		}

		$packages = $packages_by_cart[ $cart_key ];

		// Add extra package data to array.
		if ( count( $packages ) ) {
			$packages = array_map(
				function( $key, $package, $index ) use ( $cart, $cart_key ) {
					$package['package_id']   = isset( $package['package_id'] ) ? $package['package_id'] : $key;
					$package['package_name'] = isset( $package['package_name'] ) ? $package['package_name'] : self::get_shipping_package_name( $package, $cart );
					return $package;
				},
				array_keys( $packages ),
				$packages,
				range( 1, count( $packages ) )
			);
		}

		return $packages;
	}

	/**
	 * Changes the shipping package name to add more meaningful information about it's content.
	 *
	 * @param array $package All shipping package data.
	 * @param array $cart Recurring cart data.
	 * @return string
	 */
	private static function get_shipping_package_name( $package, $cart ) {
		$package_name = __( 'Shipping', 'woocommerce-subscriptions' );
		$interval     = wcs_cart_pluck( $cart, 'subscription_period_interval', '' );
		$period       = wcs_cart_pluck( $cart, 'subscription_period', '' );
		switch ( $period ) {
			case 'year':
				// translators: %d subscription interval.
				$package_name = $interval > 1 ? sprintf( _n( 'Shipment every %d year', 'Shipment every %d years', $interval, 'woocommerce-subscriptions' ), $interval ) : __( 'Yearly Shipment', 'woocommerce-subscriptions' );
				break;
			case 'month':
				// translators: %d subscription interval.
				$package_name = $interval > 1 ? sprintf( _n( 'Shipment every %d month', 'Shipment every %d months', $interval, 'woocommerce-subscriptions' ), $interval ) : __( 'Monthly Shipment', 'woocommerce-subscriptions' );
				break;
			case 'week':
				// translators: %d subscription interval.
				$package_name = $interval > 1 ? sprintf( _n( 'Shipment every %d week', 'Shipment every %d weeks', $interval, 'woocommerce-subscriptions' ), $interval ) : __( 'Weekly Shipment', 'woocommerce-subscriptions' );
				break;
			case 'day':
				// translators: %d subscription interval.
				$package_name = $interval > 1 ? sprintf( _n( 'Shipment every %d day', 'Shipment every %d days', $interval, 'woocommerce-subscriptions' ), $interval ) : __( 'Daily Shipment', 'woocommerce-subscriptions' );
				break;
		}
		return $package_name;
	}

	/**
	 * Register future subscriptions into cart endpoint.
	 *
	 * @return array $future_subscriptions Registered data or empty array if condition is not satisfied.
	 */
	public static function extend_cart_data() {
		// return early if we don't have any subscription.
		if ( ! WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return array();
		}

		$future_subscriptions = array();
		$money_formatter      = self::$extend->get_formatter( 'money' );

		if ( ! empty( wc()->cart->recurring_carts ) ) {
			foreach ( wc()->cart->recurring_carts as $cart_key => $cart ) {
				$cart_item         = current( $cart->cart_contents );
				$product           = $cart_item['data'];
				$shipping_packages = self::get_packages_for_recurring_cart( $cart_key, $cart );

				$future_subscriptions[] = array(
					'key'                 => $cart_key,
					'next_payment_date'   => $cart->next_payment_date ? date_i18n( wc_date_format(), wcs_date_to_time( get_date_from_gmt( $cart->next_payment_date ) ) ) : null,
					'billing_period'      => WC_Subscriptions_Product::get_period( $product ),
					'billing_interval'    => (int) WC_Subscriptions_Product::get_interval( $product ),
					'subscription_length' => (int) WC_Subscriptions_Product::get_length( $product ),
					'totals'              => self::$extend->get_formatter( 'currency' )->format(
						array(
							'total_items'        => $money_formatter->format( $cart->get_subtotal() ),
							'total_items_tax'    => $money_formatter->format( $cart->get_subtotal_tax() ),
							'total_fees'         => $money_formatter->format( $cart->get_fee_total() ),
							'total_fees_tax'     => $money_formatter->format( $cart->get_fee_tax() ),
							'total_discount'     => $money_formatter->format( $cart->get_discount_total() ),
							'total_discount_tax' => $money_formatter->format( $cart->get_discount_tax() ),
							'total_shipping'     => $money_formatter->format( $cart->get_shipping_total() ),
							'total_shipping_tax' => $money_formatter->format( $cart->get_shipping_tax() ),
							'total_price'        => $money_formatter->format( $cart->get_total( 'edit' ) ),
							'total_tax'          => $money_formatter->format( $cart->get_total_tax() ),
							'tax_lines'          => self::get_tax_lines( $cart ),
						)
					),
					'shipping_rates'      => array_values( array_map( array( self::$schema->get( 'cart-shipping-rate' ), 'get_item_response' ), $shipping_packages ) ),
				);
			}
		}

		return $future_subscriptions;
	}

	/**
	 * Format sign-up fees.
	 *
	 * @param \WC_Product $product current product.
	 * @return array
	 */
	private static function format_sign_up_fees( $product ) {
		$fees_excluding_tax = wcs_get_price_excluding_tax(
			$product,
			array(
				'qty'   => 1,
				'price' => WC_Subscriptions_Product::get_sign_up_fee( $product ),
			)
		);

		$fees_including_tax = wcs_get_price_including_tax(
			$product,
			array(
				'qty'   => 1,
				'price' => WC_Subscriptions_Product::get_sign_up_fee( $product ),
			)
		);

		$money_formatter = self::$extend->get_formatter( 'money' );

		return array(
			'sign_up_fees'     => $money_formatter->format(
				$fees_excluding_tax
			),
			'sign_up_fees_tax' => $money_formatter->format(
				$fees_including_tax
				- $fees_excluding_tax
			),

		);
	}

	/**
	 * Format sync data to the correct so it either returns a day integer or an object of day and month.
	 *
	 * @param WC_Product_Subscription $product current cart item product.
	 *
	 * @return object|int|null synchronization_date;
	 */
	private static function format_sync_data( $product ) {
		if ( ! WC_Subscriptions_Synchroniser::is_product_synced( $product ) ) {
			return null;
		}

		$payment_day = WC_Subscriptions_Synchroniser::get_products_payment_day( $product );

		if ( ! is_array( $payment_day ) ) {
			return (int) $payment_day;
		}

		return (object) array(
			'month' => (int) $payment_day['month'],
			'day'   => (int) $payment_day['day'],
		);
	}
	/**
	 * Register future subscriptions schema into cart endpoint.
	 *
	 * @return array Registered schema.
	 */
	public static function extend_cart_schema() {
		// return early if we don't have any subscription.
		if ( ! WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return array();
		}

		return array(
			'key'                 => array(
				'description' => __( 'Subscription key', 'woocommerce-subscriptions' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'next_payment_date'   => array(
				'description' => __( "The subscription's next payment date.", 'woocommerce-subscriptions' ),
				'type'        => [ 'date-time', 'null' ],
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'billing_period'      => array(
				'description' => __( 'Billing period for the subscription.', 'woocommerce-subscriptions' ),
				'type'        => 'string',
				'enum'        => array_keys( wcs_get_subscription_period_strings() ),
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'billing_interval'    => array(
				'description' => __( 'The number of billing periods between subscription renewals.', 'woocommerce-subscriptions' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'subscription_length' => array(
				'description' => __( 'Subscription length.', 'woocommerce-subscriptions' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'totals'              => array(
				'description' => __( 'Cart total amounts provided using the smallest unit of the currency.', 'woocommerce-subscriptions' ),
				'type'        => 'object',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'properties'  => array(
					'total_items'                 => array(
						'description' => __( 'Total price of items in the cart.', 'woocommerce-subscriptions' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'total_items_tax'             => array(
						'description' => __( 'Total tax on items in the cart.', 'woocommerce-subscriptions' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'total_fees'                  => array(
						'description' => __( 'Total price of any applied fees.', 'woocommerce-subscriptions' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'total_fees_tax'              => array(
						'description' => __( 'Total tax on fees.', 'woocommerce-subscriptions' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'total_discount'              => array(
						'description' => __( 'Total discount from applied coupons.', 'woocommerce-subscriptions' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'total_discount_tax'          => array(
						'description' => __( 'Total tax removed due to discount from applied coupons.', 'woocommerce-subscriptions' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'total_shipping'              => array(
						'description' => __( 'Total price of shipping. If shipping has not been calculated, a null response will be sent.', 'woocommerce-subscriptions' ),
						'type'        => array( 'string', 'null' ),
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'total_shipping_tax'          => array(
						'description' => __( 'Total tax on shipping. If shipping has not been calculated, a null response will be sent.', 'woocommerce-subscriptions' ),
						'type'        => array( 'string', 'null' ),
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'total_price'                 => array(
						'description' => __( 'Total price the customer will pay.', 'woocommerce-subscriptions' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'total_tax'                   => array(
						'description' => __( 'Total tax applied to items and shipping.', 'woocommerce-subscriptions' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'tax_lines'                   => array(
						'description' => __( 'Lines of taxes applied to items and shipping.', 'woocommerce-subscriptions' ),
						'type'        => 'array',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'name'  => array(
									'description' => __( 'The name of the tax.', 'woocommerce-subscriptions' ),
									'type'        => 'string',
									'context'     => array( 'view', 'edit' ),
									'readonly'    => true,
								),
								'price' => array(
									'description' => __( 'The amount of tax charged.', 'woocommerce-subscriptions' ),
									'type'        => 'string',
									'context'     => array( 'view', 'edit' ),
									'readonly'    => true,
								),
							),
						),
					),
					'currency_code'               => array(
						'description' => __( 'Currency code (in ISO format) for returned prices.', 'woocommerce-subscriptions' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'currency_symbol'             => array(
						'description' => __( 'Currency symbol for the currency which can be used to format returned prices.', 'woocommerce-subscriptions' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'currency_minor_unit'         => array(
						'description' => __( 'Currency minor unit (number of digits after the decimal separator) for returned prices.', 'woocommerce-subscriptions' ),
						'type'        => 'integer',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'currency_decimal_separator'  => array(
						'description' => __( 'Decimal separator for the currency which can be used to format returned prices.', 'woocommerce-subscriptions' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'currency_thousand_separator' => array(
						'description' => __( 'Thousand separator for the currency which can be used to format returned prices.', 'woocommerce-subscriptions' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'currency_prefix'             => array(
						'description' => __( 'Price prefix for the currency which can be used to format returned prices.', 'woocommerce-subscriptions' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
					'currency_suffix'             => array(
						'description' => __( 'Price prefix for the currency which can be used to format returned prices.', 'woocommerce-subscriptions' ),
						'type'        => 'string',
						'context'     => array( 'view', 'edit' ),
						'readonly'    => true,
					),
				),
			),
		);
	}

	/**
	 * Get tax lines from the cart and format to match schema.
	 *
	 * TODO: This function is copied from WooCommerce Blocks, remove it once https://github.com/woocommerce/woocommerce-gutenberg-products-block/issues/3264 is closed.
	 *
	 * @param \WC_Cart $cart Cart class instance.
	 * @return array
	 */
	protected static function get_tax_lines( $cart ) {
		$cart_tax_totals = $cart->get_tax_totals();
		$tax_lines       = array();

		foreach ( $cart_tax_totals as $cart_tax_total ) {
			$tax_lines[] = array(
				'name'  => $cart_tax_total->label,
				'price' => self::$extend->get_formatter( 'money' )->format( $cart_tax_total->amount ),
			);
		}

		return $tax_lines;
	}
}
