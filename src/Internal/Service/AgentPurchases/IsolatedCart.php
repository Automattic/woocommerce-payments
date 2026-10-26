<?php
/**
 * Isolated cart for agent purchase quotes.
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Service\AgentPurchases;

/** A cart that never loads or persists a shopper session. */
class IsolatedCart extends \WC_Cart {
	/** Initialize only the totals engine dependencies. */
	public function __construct() {
		$this->fees_api = new \WC_Cart_Fees();
	}

	/**
	 * Retrieve the cart without loading a storefront session.
	 *
	 * @return array Cart contents.
	 */
	public function get_cart() {
		return array_filter( $this->get_cart_contents() );
	}
}
