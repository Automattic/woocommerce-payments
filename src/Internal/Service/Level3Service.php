<?php
/**
 * Class Level3Service
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service;

use stdClass;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Order_Item_Fee;
use WC_Payments_Account;
use WC_Payments_Utils;
use WCPay\Internal\Service\OrderService;
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

		$process_item  = function ( $item ) use ( $currency ) {
			return $this->process_item( $item, $currency );
		};
		$items_to_send = array_map( $process_item, $order_items );

		if ( count( $items_to_send ) > 200 ) {
			// If more than 200 items are present, bundle the last ones in a single item.
			$items_to_send = array_merge(
				array_slice( $items_to_send, 0, 199 ),
				[ $this->bundle_level3_data_from_items( array_slice( $items_to_send, 200 ) ) ]
			);
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

		return $level3_data;
	}

	/**
	 * Processes a single order item.
	 * Based on the queried items, this class should only receive
	 * `WC_Order_Item_Product` or `WC_Order_Item_Fee` line items.
	 *
	 * @param WC_Order_Item_Product|WC_Order_Item_Fee $item     Item to process.
	 * @param string                                  $currency Currency to use.
	 * @return \stdClass
	 */
	private function process_item( WC_Order_Item $item, string $currency ): stdClass {
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
		}

		$description = substr( $item->get_name(), 0, 26 );
		$quantity    = ceil( $item->get_quantity() );
		$tax_amount  = $this->prepare_amount( $item->get_total_tax(), $currency );
		if ( $subtotal >= 0 ) {
			$unit_cost       = $this->prepare_amount( $subtotal / $quantity, $currency );
			$discount_amount = $this->prepare_amount( $subtotal - $item->get_total(), $currency );
		} else {
			// It's possible to create products with negative price - represent it as free one with discount.
			$discount_amount = abs( $this->prepare_amount( $subtotal / $quantity, $currency ) );
			$unit_cost       = 0;
		}

		return (object) [
			'product_code'        => (string) $product_code, // Up to 12 characters that uniquely identify the product.
			'product_description' => $description, // Up to 26 characters long describing the product.
			'unit_cost'           => $unit_cost, // Cost of the product, in cents, as a non-negative integer.
			'quantity'            => $quantity, // The number of items of this type sold, as a non-negative integer.
			'tax_amount'          => $tax_amount, // The amount of tax this item had added to it, in cents, as a non-negative integer.
			'discount_amount'     => $discount_amount, // The amount an item was discounted—if there was a sale,for example, as a non-negative integer.
		];
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
			'product_code'        => (string) substr( uniqid(), 0, 26 ),
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
