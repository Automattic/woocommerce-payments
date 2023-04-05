<?php
/**
 * Class WooCommerceBlocks
 *
 * @package WCPay\MultiCurrency\Compatibility
 */

namespace WCPay\MultiCurrency\Compatibility;

use WCPay\MultiCurrency\MultiCurrency;
use WCPay\MultiCurrency\Utils;

/**
 * Class that controls Multi Currency Compatibility with WooCommerce Blocks Plugin.
 */
class WooCommerceBlocks extends BaseCompatibility {

	/**
	 * Init the class.
	 *
	 * @return  void
	 */
	protected function init() {
        add_filter( 'woocommerce_blocks_min_price', [ $this, 'convert_price_to_user_currency' ] );
        add_filter( 'woocommerce_blocks_max_price', [ $this, 'convert_price_to_user_currency' ] );
	}

	public function convert_price_to_user_currency( $price ): float {
		return $this->multi_currency->get_price( $price, 'product' );
	}

    public function convert_price_to_default_currency( $price ): float {
		/**
		 * We would like this function to return the price in the default currency.
		 */
	}
}
