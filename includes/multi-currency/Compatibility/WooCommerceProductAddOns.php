<?php
/**
 * Class WooCommerceProductAddOns
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WC_Product;
use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce Product Add Ons Plugin.
 */
class WooCommerceProductAddOns extends BaseCompatibility {

	const ADDONS_CONVERTED_META_KEY = '_wcpay_multi_currency_addons_converted';

	/**
	 * Init the class.
	 *
	 * @return  void
	 */
	protected function init() {
		// Add needed actions and filters if Product Add Ons is active.
		if ( class_exists( 'WC_Product_Addons' ) ) {
			if ( ! is_admin() && ! defined( 'DOING_CRON' ) ) {
				add_filter( 'woocommerce_product_addons_option_price_raw', [ $this, 'get_addons_price' ], 50, 2 );
				add_filter( 'woocommerce_product_addons_price_raw', [ $this, 'get_addons_price' ], 50, 2 );
				add_filter( 'woocommerce_product_addons_params', [ $this, 'product_addons_params' ], 50, 1 );
				add_filter( 'woocommerce_product_addons_get_item_data', [ $this, 'get_item_data' ], 50, 3 );
				add_filter( 'woocommerce_product_addons_update_product_price', [ $this, 'update_product_price' ], 50, 4 );
				add_filter( 'woocommerce_product_addons_order_line_item_meta', [ $this, 'order_line_item_meta' ], 50, 4 );
				add_filter( MultiCurrency::FILTER_PREFIX . 'should_convert_product_price', [ $this, 'should_convert_product_price' ], 50, 2 );
			}

			if ( wp_doing_ajax() ) {
				add_filter( 'woocommerce_product_addons_ajax_get_product_price_including_tax', [ $this, 'get_product_calculation_price' ], 50, 3 );
				add_filter( 'woocommerce_product_addons_ajax_get_product_price_excluding_tax', [ $this, 'get_product_calculation_price' ], 50, 3 );
			}
		}
	}

	/**
	 * Checks to see if the product's price should be converted.
	 *
	 * @param bool   $return  Whether to convert the product's price or not. Default is true.
	 * @param object $product Product object to test.
	 *
	 * @return bool True if it should be converted.
	 */
	public function should_convert_product_price( bool $return, $product ): bool {
		// If it's already false, return it.
		if ( ! $return ) {
			return $return;
		}

		// Check for cart items to see if they have already been converted.
		if ( 1 === $product->get_meta( self::ADDONS_CONVERTED_META_KEY ) ) {
			return false;
		}

		return $return;
	}

	/**
	 * Converts the price of an addon from WooCommerce Products Add-on extension.
	 *
	 * @param mixed $price   The price to be filtered.
	 * @param array $type    The type of the addon.

	 * @return mixed         The price as a string or float.
	 */
	public function get_addons_price( $price, $type ) {
		if ( 'percentage_based' === $type['price_type'] ) {
			// If the addon is a percentage_based type $price is actually a percentage
			// and doesn't need any conversion.
			return $price;
		}

		return $this->multi_currency->get_price( $price, 'product' );
	}

	/**
	 * Fixes currency formatting issues in Product Add-Ons. PAO gets these values directly from the db options,
	 * so those values aren't filtered. Luckily, there's a filter.
	 *
	 * @param array $params Product Add-Ons global parameters.
	 *
	 * @return array Adjust parameters.
	 */
	public function product_addons_params( array $params ): array {
		$params['currency_format_num_decimals'] = wc_get_price_decimals();
		$params['currency_format_decimal_sep']  = wc_get_price_decimal_separator();
		$params['currency_format_thousand_sep'] = wc_get_price_thousand_separator();

		return $params;
	}

	/**
	 * Filters the cart item data meta so we can provide the proper name with converted add on price.
	 *
	 * @param array $addon_data The addon data we are filtering/replacing.
	 * @param array $addon      The addon being processed.
	 * @param array $cart_item  The cart item being processed.
	 *
	 * @return array
	 */
	public function get_item_data( $addon_data, $addon, $cart_item ): array {
		$price = isset( $cart_item['addons_price_before_calc'] ) ? $cart_item['addons_price_before_calc'] : $addon['price'];
		$value = $addon['value'];

		/*
		 * 'woocommerce_addons_add_cart_price_to_value'
		 *
		 * Use this filter to display the price next to each selected add-on option.
		 * By default, add-on prices show up only next to flat fee add-ons.
		 *
		 * @param boolean
		 */
		$add_price_to_value = apply_filters( 'woocommerce_addons_add_cart_price_to_value', false, $cart_item );

		if ( 0.0 === (float) $addon['price'] ) {
			$value .= '';
		} elseif ( 'percentage_based' === $addon['price_type'] && 0.0 === (float) $price ) {
			$value .= '';
		} elseif ( 'custom_price' === $addon['field_type'] && $addon['price'] ) {
			if ( class_exists( 'WC_Product_Addons_Helper' ) ) {
				$addon_price = wc_price( \WC_Product_Addons_Helper::get_product_addon_price_for_display( $addon['price'], $cart_item['data'] ) );
				/* translators: %1$s custom addon price in cart */
				$value           .= sprintf( _x( ' (%1$s)', 'custom price addon price in cart', 'woocommerce-payments' ), $addon_price );
				$addon['display'] = $value;
			}
		} elseif ( 'flat_fee' === $addon['price_type'] && $addon['price'] ) {
			if ( class_exists( 'WC_Product_Addons_Helper' ) ) {
				$addon_price = $this->multi_currency->get_price( $addon['price'], 'product' );
				if ( 'input_multiplier' === $addon['field_type'] ) {
					// Quantity/multiplier add on needs to be split, calculated, then multiplied by input value.
					$addon_price = $this->multi_currency->get_price( $addon['price'] / $addon['value'], 'product' ) * $addon['value'];
				}
				$addon_price = wc_price( \WC_Product_Addons_Helper::get_product_addon_price_for_display( $addon_price, $cart_item['data'] ) );
				/* translators: %1$s flat fee addon price in order */
				$value .= sprintf( _x( ' (+ %1$s)', 'flat fee addon price in cart', 'woocommerce-payments' ), $addon_price );
			}
		} elseif ( 'quantity_based' === $addon['price_type'] && $addon['price'] && $add_price_to_value ) {
			if ( class_exists( 'WC_Product_Addons_Helper' ) ) {
				$addon_price = $this->multi_currency->get_price( $addon['price'], 'product' );
				if ( 'input_multiplier' === $addon['field_type'] ) {
					// Quantity/multiplier add on needs to be split, calculated, then multiplied by input value.
					$addon_price = $this->multi_currency->get_price( $addon['price'] / $addon['value'], 'product' ) * $addon['value'];
				}
				$addon_price = wc_price( \WC_Product_Addons_Helper::get_product_addon_price_for_display( $addon_price, $cart_item['data'] ) );
				/* translators: %1$s addon price in order */
				$value .= sprintf( _x( ' (%1$s)', 'quantity based addon price in cart', 'woocommerce-payments' ), $addon_price );
			}
		} elseif ( 'percentage_based' === $addon['price_type'] && $addon['price'] && $add_price_to_value ) {
			// Get the percentage cost in the currency in use, and set the meta data on the product that the value was converted.
			$_product = wc_get_product( $cart_item['product_id'] );
			$price    = $this->multi_currency->get_price( $price, 'product' );
			$_product->set_price( $price * ( $addon['price'] / 100 ) );
			$_product->update_meta_data( self::ADDONS_CONVERTED_META_KEY, 1 );
			/* translators: %1$s addon price in order */
			$value .= sprintf( _x( ' (%1$s)', 'percentage based addon price in cart', 'woocommerce-payments' ), WC()->cart->get_product_price( $_product ) );
		}

		return [
			'name'    => $addon['name'],
			'value'   => $value,
			'display' => isset( $addon['display'] ) ? $addon['display'] : '',
		];
	}

	/**
	 * Updates the product price according to converted add on values.
	 *
	 * @param array $updated_prices Prices updated by Product Add-Ons (unused).
	 * @param array $cart_item      Cart item meta data.
	 * @param array $prices         Original prices passed to Product Add-Ons for calculations.
	 *
	 * @return array
	 */
	public function update_product_price( $updated_prices, $cart_item, $prices ): array {
		$price                       = $this->multi_currency->get_price( $prices['price'], 'product' );
		$regular_price               = $this->multi_currency->get_price( $prices['regular_price'], 'product' );
		$sale_price                  = $this->multi_currency->get_price( $prices['sale_price'], 'product' );
		$flat_fees                   = 0;
		$quantity                    = $cart_item['quantity'];
		$price_before_addons         = $price;
		$regular_price_before_addons = $regular_price;
		$sale_price_before_addons    = $sale_price;

		// TODO: Check compat with Smart Coupons.
		// Compatibility with Smart Coupons self declared gift amount purchase.
		$credit_called = ! empty( $_POST['credit_called'] ) ? $_POST['credit_called'] : null;  // phpcs:ignore
		if ( empty( $price ) && ! empty( $credit_called ) ) {
			// Variable $_POST['credit_called'] is an array.
			if ( isset( $credit_called[ $cart_item['data']->get_id() ] ) ) {
				$price         = (float) $credit_called[ $cart_item['data']->get_id() ];
				$regular_price = $price;
				$sale_price    = $price;
			}
		}

		if ( empty( $price ) && ! empty( $cart_item['credit_amount'] ) ) {
			$price         = (float) $cart_item['credit_amount'];
			$regular_price = $price;
			$sale_price    = $price;
		}

		foreach ( $cart_item['addons'] as $addon ) {
			// Percentage based and custom defined addon prices do not get converted, all others do.
			if ( 'percentage_based' === $addon['price_type'] || 'custom_price' === $addon['field_type'] ) {
				$addon_price = $addon['price'];
			} elseif ( 'input_multiplier' === $addon['field_type'] ) {
				// Quantity/multiplier add on needs to be split, calculated, then multiplied by input value.
				$addon_price = $this->multi_currency->get_price( $addon['price'] / $addon['value'], 'product' ) * $addon['value'];
			} else {
				$addon_price = $this->multi_currency->get_price( $addon['price'], 'product' );
			}

			switch ( $addon['price_type'] ) {
				case 'percentage_based':
					$price         += (float) ( $price_before_addons * ( $addon_price / 100 ) );
					$regular_price += (float) ( $regular_price_before_addons * ( $addon_price / 100 ) );
					$sale_price    += (float) ( $sale_price_before_addons * ( $addon_price / 100 ) );
					break;
				case 'flat_fee':
					$flat_fee       = $quantity > 0 ? (float) ( $addon_price / $quantity ) : 0;
					$price         += $flat_fee;
					$regular_price += $flat_fee;
					$sale_price    += $flat_fee;
					$flat_fees     += $flat_fee;
					break;
				default:
					$price         += (float) $addon_price;
					$regular_price += (float) $addon_price;
					$sale_price    += (float) $addon_price;
					break;
			}
		}

		// Let ourselves know this item has had add ons converted.
		$cart_item['data']->update_meta_data( self::ADDONS_CONVERTED_META_KEY, 1 );

		return [
			'price'                => $price,
			'regular_price'        => $regular_price,
			'sale_price'           => $sale_price,
			'addons_flat_fees_sum' => $flat_fees,
		];
	}

	/**
	 * Filters the meta data for order line items so that we can properly set values in the names.
	 *
	 * @param array                  $meta_data A key/value for the meta data to be inserted for the line item.
	 * @param array                  $addon     The addon being processed.
	 * @param \WC_Order_Item_Product $item      Order item data.
	 * @param array                  $values    Order item values.
	 *
	 * @return array A key/value for the meta data to be inserted for the line item.
	 */
	public function order_line_item_meta( array $meta_data, array $addon, \WC_Order_Item_Product $item, array $values ): array {

		$add_price_to_value = apply_filters( 'woocommerce_addons_add_order_price_to_value', false, $item );

		$value = $addon['value'];

		// Pass the timestamp as the add-on value in order to save the timestamp to the DB.
		if ( isset( $addon['timestamp'] ) ) {
			$value = $addon['timestamp'];
		}

		// If there is an add-on price, add the price of the add-on to the label name.
		if ( $addon['price'] && $add_price_to_value ) {
			$product = $item->get_product();

			if ( 'percentage_based' === $addon['price_type'] && 0.0 !== (float) $product->get_price() ) {
				// Calculate the percentage price.
				$addon_price = $product->get_price() * ( $addon['price'] / 100 );
			} elseif ( 'custom_price' === $addon['field_type'] ) {
				// Custom prices do not get converted.
				$addon_price = $addon['price'];
			} elseif ( 'input_multiplier' === $addon['field_type'] ) {
				// Quantity/multiplier add on needs to be split, calculated, then multiplied by input value.
				$addon_price = $this->multi_currency->get_price( $addon['price'] / $addon['value'], 'product' ) * $addon['value'];
			} else {
				// Convert all others.
				$addon_price = $this->multi_currency->get_price( $addon['price'], 'product' );
			}
			if ( class_exists( 'WC_Product_Addons_Helper' ) ) {
				$price = html_entity_decode(
					wp_strip_all_tags( wc_price( \WC_Product_Addons_Helper::get_product_addon_price_for_display( $addon_price, $values['data'] ) ) ),
					ENT_QUOTES,
					get_bloginfo( 'charset' )
				);
			}

			if ( 'flat_fee' === $addon['price_type'] && $addon['price'] && $add_price_to_value ) {
				/* translators: %1$s flat fee addon price in order */
				$value .= sprintf( _x( ' (+ %1$s)', 'flat fee addon price in order', 'woocommerce-payments' ), $price );
			} elseif ( ( 'quantity_based' === $addon['price_type'] || 'percentage_based' === $addon['price_type'] ) && $addon['price'] && $add_price_to_value ) {
				/* translators: %1$s addon price in order */
				$value .= sprintf( _x( ' (%1$s)', 'addon price in order', 'woocommerce-payments' ), $price );
			} elseif ( 'custom_price' === $addon['field_type'] ) {
				/* translators: %1$s custom addon price in order */
				$value = sprintf( _x( ' (%1$s)', 'custom addon price in order', 'woocommerce-payments' ), $price );
			}

			$meta_data['raw_price'] = $this->multi_currency->get_price( $addon['price'], 'product' );
		}

		$meta_data['value'] = $value;
		return $meta_data;
	}

	/**
	 * Gets the product price during ajax requests from the product page.
	 *
	 * @param float       $price    Price to get converted.
	 * @param int         $quantity Quantity of the product selected.
	 * @param \WC_Product $product  WC_Product related to the price.
	 *
	 * @return float Adjusted price.
	 */
	public function get_product_calculation_price( float $price, int $quantity, \WC_Product $product ): float {
		return $this->multi_currency->get_price( $price / $quantity, 'product' ) * $quantity;
	}
}
