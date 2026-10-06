<?php
/**
 * Class Level3Service
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use WC_Order;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Order_Item_Fee;
use WC_Payments_Account;
use WC_Payments_Utils;
use WCPay\Exceptions\Order_Not_Found_Exception;
use WCPay\Internal\Proxy\LegacyProxy;

/**
 * Service for generating Level 3 data from orders.
 */
class Level3Service {
	/**
	 * Order service.
	 *
	 * @var OrderService
	 */
	private $order_service;

	/**
	 * WC_Payments_Account instance to get information about the account
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * Legacy proxy.
	 *
	 * @var LegacyProxy
	 */
	private $legacy_proxy;

	/**
	 * Service constructor.
	 *
	 * @param OrderService        $order_service Order service.
	 * @param WC_Payments_Account $account       WooPayments account.
	 * @param LegacyProxy         $legacy_proxy  Legacy proxy.
	 */
	public function __construct(
		OrderService $order_service,
		WC_Payments_Account $account,
		LegacyProxy $legacy_proxy
	) {
		$this->order_service = $order_service;
		$this->account       = $account;
		$this->legacy_proxy  = $legacy_proxy;
	}

	/**
	 * Create the level 3 data array to send to Stripe when making a purchase.
	 *
	 * @param int $order_id The order that is being paid for.
	 * @return array        The level 3 data to send to the API.
	 * @throws Order_Not_Found_Exception
	 */
	public function get_data_from_order( int $order_id ): array {
		$order = $this->order_service->_deprecated_get_order( $order_id );

		$merchant_country = $this->account->get_account_country();
		// We do not need to send level3 data if merchant account country is non-US.
		if ( 'US' !== $merchant_country ) {
			return [];
		}

		// Get the order items. Don't need their keys, only their values.
		// Order item IDs are used as keys in the original order items array.
		$order_items = array_values( $order->get_items( [ 'line_item', 'fee' ] ) );
		$currency    = $order->get_currency();

		$items_to_send = [];
		foreach ( $order_items as $item ) {
			$items_to_send = array_merge( $items_to_send, $this->process_item( $item, $currency ) );
		}

		$level3_data = [
			'merchant_reference' => (string) $order->get_id(), // An alphanumeric string of up to  characters in length. This unique value is assigned by the merchant to identify the order. Also known as an “Order ID”.
			'customer_reference' => (string) $order->get_id(),
			'shipping_amount'    => $this->prepare_amount( (float) $order->get_shipping_total() + (float) $order->get_shipping_tax(), $currency ), // The shipping cost, in cents, as a non-negative integer.
			'line_items'         => $items_to_send,
		];

		// The customer’s U.S. shipping ZIP code.
		$shipping_address_zip = $order->get_shipping_postcode();
		if ( WC_Payments_Utils::is_valid_us_zip_code( $shipping_address_zip ) ) {
			$level3_data['shipping_address_zip'] = $shipping_address_zip;
		}

		// The merchant’s U.S. shipping ZIP code.
		$store_postcode = $this->legacy_proxy->call_function( 'get_option', 'woocommerce_store_postcode' );
		if ( WC_Payments_Utils::is_valid_us_zip_code( $store_postcode ) ) {
			$level3_data['shipping_from_zip'] = $store_postcode;
		}

		/**
		 * Filters the Level 3 data based on order.
		 *
		 * Example usage: Enables updating the discount based on the products in the order,
		 * if any of the products are gift cards.
		 *
		 * @since 8.0.0
		 *
		 * @param array $level3_data Precalculated Level 3 data based on order.
		 * @param WC_Order $order    The order object.
		 */
		$level3_data = apply_filters( 'wcpay_payment_request_level3_data', $level3_data, $order );

		$level3_data = $this->reconcile_with_order_total( $level3_data, $order );
		if ( empty( $level3_data ) ) {
			return [];
		}

		if ( count( $level3_data['line_items'] ) > 200 ) {
			// If more than 200 items are present, bundle the last ones in a single item.
			$items_to_send = array_merge(
				array_slice( $level3_data['line_items'], 0, 199 ),
				[ $this->bundle_level3_data_from_items( array_slice( $level3_data['line_items'], 199 ) ) ]
			);

			$level3_data['line_items'] = $items_to_send;
		}

		return $level3_data;
	}

	/**
	 * Processes a single order item.
	 * Based on the queried items, this class should only receive
	 * `WC_Order_Item_Product` or `WC_Order_Item_Fee` line items.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Fee $item     Item to process.
	 * @param string                                  $currency Currency to use.
	 * @return \stdClass[]
	 */
	private function process_item( WC_Order_Item $item, string $currency ): array {
		// Check to see if it is a WC_Order_Item_Product or a WC_Order_Item_Fee.
		if ( $item instanceof WC_Order_Item_Product ) {
			$subtotal     = $item->get_subtotal();
			$product_id   = $item->get_variation_id()
				? $item->get_variation_id()
				: $item->get_product_id();
			$product_code = substr( $product_id, 0, 12 );
		} else {
			$subtotal     = $item->get_total();
			$product_code = substr( sanitize_title( $item->get_name() ), 0, 12 );
			// Stripe rejects empty codes, and symbol-only fee names sanitize to nothing.
			if ( '' === $product_code ) {
				$product_code = 'fee';
			}
		}

		$description = substr( $item->get_name(), 0, 26 );
		// Stripe rejects empty descriptions.
		if ( '' === trim( $description ) ) {
			$description = __( 'Item', 'woocommerce-payments' );
		}

		$quantity   = ceil( $item->get_quantity() );
		$tax_amount = $this->prepare_amount( $item->get_total_tax(), $currency );
		if ( $subtotal >= 0 ) {
			$unit_cost       = $this->prepare_amount( $subtotal / $quantity, $currency );
			$discount_amount = $this->prepare_amount( $subtotal - $item->get_total(), $currency );
		} else {
			// It's possible to create products with negative price - represent it as free one with discount.
			$discount_amount = abs( $this->prepare_amount( $item->get_total(), $currency ) );
			$unit_cost       = 0;
		}

		// Tax also shouldn't be negative so represent it as a discount.
		if ( $tax_amount < 0 ) {
			$discount_amount += abs( $tax_amount );
			$tax_amount       = 0;
		}

		$line_item = (object) [
			'product_code'        => (string) $product_code, // Up to 12 characters that uniquely identify the product.
			'product_description' => $description, // Up to 26 characters long describing the product.
			'unit_cost'           => $unit_cost, // Cost of the product, in cents, as a non-negative integer.
			'quantity'            => $quantity, // The number of items of this type sold, as a non-negative integer.
			'tax_amount'          => $tax_amount, // The amount of tax this item had added to it, in cents, as a non-negative integer.
			'discount_amount'     => $discount_amount, // The amount an item was discounted—if there was a sale,for example, as a non-negative integer.
		];

		return [ $line_item ];
	}

	/**
	 * Makes the Level 3 line items add up to the order total, or drops Level 3 data when they can't.
	 *
	 * Stripe needs an exact match. Rounding gaps get a `rounding-fix` line; bigger gaps (deposits, store credit) and
	 * invalid values drop the data. Descriptions are cut to 26 characters, the most card networks keep.
	 *
	 * @param mixed    $level3_data Level 3 data, as returned by the filter.
	 * @param WC_Order $order       The order being paid for.
	 *
	 * @return array The Level 3 data, or an empty array when none should be sent.
	 */
	private function reconcile_with_order_total( $level3_data, WC_Order $order ): array {
		if (
			! is_array( $level3_data )
			|| ! is_array( $level3_data['line_items'] ?? null )
			|| ! $this->is_level3_amount( $level3_data['shipping_amount'] ?? 0 )
		) {
			return [];
		}

		$level3_total = (int) ( $level3_data['shipping_amount'] ?? 0 );
		// Shipping is rounded once: up to a cent.
		$max_rounding_error = 1;
		foreach ( $level3_data['line_items'] as $index => $line_item ) {
			$line_item = (array) $line_item;
			if ( is_string( $line_item['product_description'] ?? null ) && mb_strlen( $line_item['product_description'] ) > 26 ) {
				$line_item['product_description']    = mb_substr( $line_item['product_description'], 0, 26 );
				$level3_data['line_items'][ $index ] = (object) $line_item;
			}

			$tax_amount      = $line_item['tax_amount'] ?? 0;
			$discount_amount = $line_item['discount_amount'] ?? 0;
			if (
				! $this->is_level3_string( $line_item['product_code'] ?? null, 12 )
				|| ! $this->is_level3_string( $line_item['product_description'] ?? null, 26 )
				|| ! $this->is_level3_amount( $line_item['unit_cost'] ?? null )
				|| ! $this->is_level3_amount( $line_item['quantity'] ?? null )
				|| ! $this->is_level3_amount( $tax_amount )
				|| ! $this->is_level3_amount( $discount_amount )
			) {
				return [];
			}

			$quantity      = (int) $line_item['quantity'];
			$level3_total += (int) $line_item['unit_cost'] * $quantity + (int) $tax_amount - (int) $discount_amount;
			// Half a cent per unit from the rounded unit cost, plus a cent for tax and discount.
			$max_rounding_error += (int) ceil( $quantity / 2 ) + 1;
		}

		$difference = $this->prepare_amount( (float) $order->get_total(), $order->get_currency() ) - $level3_total;
		if ( 0 === $difference ) {
			return $level3_data;
		}

		if ( abs( $difference ) > $max_rounding_error ) {
			return [];
		}

		$level3_data['line_items'][] = (object) [
			'product_code'        => 'rounding-fix',
			'product_description' => mb_substr( __( 'Rounding fix', 'woocommerce-payments' ), 0, 26 ),
			'unit_cost'           => max( 0, $difference ),
			'quantity'            => 1,
			'tax_amount'          => 0,
			'discount_amount'     => max( 0, -$difference ),
		];

		return $level3_data;
	}

	/**
	 * Whether a Level 3 value is a non-negative integer. Quantities from `ceil()` are floats like 2.0.
	 *
	 * @param mixed $value Value to check.
	 *
	 * @return bool
	 */
	private function is_level3_amount( $value ): bool {
		return is_numeric( $value ) && (float) $value >= 0 && floor( (float) $value ) === (float) $value;
	}

	/**
	 * Whether a Level 3 value is a non-empty string of at most `$max_length` characters.
	 *
	 * @param mixed $value      Value to check.
	 * @param int   $max_length Maximum number of characters.
	 *
	 * @return bool
	 */
	private function is_level3_string( $value, int $max_length ): bool {
		if ( ! is_string( $value ) && ! is_int( $value ) ) {
			return false;
		}

		$length = mb_strlen( (string) $value );

		return $length > 0 && $length <= $max_length;
	}

	/**
	 * Returns a bundle of products passed as an argument. Useful when working with Stripe's level 3 data
	 *
	 * @param array $items The Stripe's level 3 array of items.
	 *
	 * @return \stdClass A bundle of the products passed.
	 */
	private function bundle_level3_data_from_items( array $items ) {
		// Total cost is the sum of each product cost * quantity.
		$items_count = count( $items );
		$total_cost  = array_sum(
			array_map(
				function ( $cost, $qty ) {
					return $cost * $qty;
				},
				array_column( $items, 'unit_cost' ),
				array_column( $items, 'quantity' )
			)
		);

		return (object) [
			'product_code'        => (string) substr( uniqid(), 0, 12 ),
			'product_description' => "{$items_count} more items",
			'unit_cost'           => $total_cost,
			'quantity'            => 1,
			'tax_amount'          => array_sum( array_column( $items, 'tax_amount' ) ),
			'discount_amount'     => array_sum( array_column( $items, 'discount_amount' ) ),
		];
	}

	/**
	 * Returns an API-ready amount based on a currency.
	 *
	 * @param float  $amount   The base amount.
	 * @param string $currency The currency for the amount.
	 *
	 * @return int The amount in cents.
	 */
	private function prepare_amount( float $amount, string $currency ): int {
		return WC_Payments_Utils::prepare_amount( $amount, $currency );
	}
}
