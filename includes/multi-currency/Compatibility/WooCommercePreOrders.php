<?php
/**
 * Class WooCommercePreOrders
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce Pre-Orders Plugin.
 */
class WooCommercePreOrders extends BaseCompatibility {

	/**
	 * Constructor.
	 *
	 * @param MultiCurrency $multi_currency MultiCurrency class.
	 * @param Utils         $utils          Utils class.
	 */
	public function __construct( MultiCurrency $multi_currency, Utils $utils ) {
		parent::__construct( $multi_currency, $utils );
		$this->initialize_hooks();
	}

	/**
	 * Adds compatibility filters if the plugin exists and loaded
	 *
	 * @return  void
	 */
	protected function initialize_hooks() {
		if ( class_exists( 'WC_Pre_Orders' ) ) {
			add_filter( 'wc_pre_orders_fee', [ $this, 'wc_pre_orders_fee' ] );
		}
	}

	/**
	 * Convert the Pre-Orders fee.
	 *
	 * @param array $args Array of args for the Pre-Orders fee.
	 *
	 * @return array
	 */
	public function wc_pre_orders_fee( array $args ): array {
		$args['amount'] = $this->multi_currency->get_price( $args['amount'], 'product' );
		return $args;
	}
}
