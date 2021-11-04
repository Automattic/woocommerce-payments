<?php
/**
 * Class WooCommerceDeposits
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce Deposits Plugin.
 */
class WooCommerceDeposits {
	/**
	 * MultiCurrency class.
	 *
	 * @var MultiCurrency
	 */
	private $multi_currency;

	/**
	 * Utils class.
	 *
	 * @var Utils
	 */
	private $utils;

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency $multi_currency MultiCurrency class.
	 * @param Utils         $utils Utils class.
	 */
	public function __construct( MultiCurrency $multi_currency, Utils $utils ) {
		$this->multi_currency = $multi_currency;
		$this->utils          = $utils;
		$this->initialize_hooks();
	}

	/**
	 * Adds compatibility filters if the plugin exists and loaded
	 *
	 * @return  void
	 */
	protected function initialize_hooks() {
		if ( class_exists( 'WC_Deposits' ) ) {
			// Add compatibility filters here.
			add_action( 'woocommerce_deposits_create_order', [ $this, 'modify_order_currency' ] );
			add_filter( 'woocommerce_get_cart_contents', [ $this, 'modify_cart_item_deposit_amounts' ] );
		}
	}

	/**
	 * Converts the currency for deposit amounts of each cart item
	 *
	 * @param array $cart_contents The current tax definitions.
	 *
	 * @return array $tax Array of altered taxes.
	 */
	public function modify_cart_item_deposit_amounts( $cart_contents ) {

		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['deposit_amount'] ) ) {
				$deposit_amount                                    = floatval( $cart_item['deposit_amount'] );
				$cart_contents[ $cart_item_key ]['deposit_amount'] = $this->multi_currency->get_price( $deposit_amount, 'product' );
			}
		}

		return $cart_contents;
	}

	/**
	 * When creating a new order for the remaining amount, forces the new order currency
	 * to be the same with the deposited order currency.
	 *
	 * @param   integer $order_id  The created order ID.
	 *
	 * @return  void
	 */
	public function modify_order_currency( $order_id ) {
		// We need to get the original order from the first item meta.
		$order       = wc_get_order( $order_id );
		$order_items = $order->get_items();
		$order_item  = 0 < count( $order_items ) ? reset( $order_items ) : null;

		if ( ! $order_item ) {
			return;
		}

		// Check if the original order ID is attached to the order item.
		$original_order_id = wc_get_order_item_meta( $order_item->get_id(), '_original_order_id', true );
		if ( ! $original_order_id ) {
			return;
		}

		// Check if the original order still exists.
		$original_order = wc_get_order( $original_order_id );
		if ( ! $original_order ) {
			return;
		}

		// Get the new and old order currencies, and match them if unmatched.
		$saved_currency    = $order->get_currency( 'view' );
		$original_currency = $original_order->get_currency( 'view' );
		if ( $saved_currency !== $original_currency ) {
			$order->set_currency( $original_currency );
			$order->save();
		}
	}
}
