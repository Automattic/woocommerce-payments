<?php
/**
 * Class Digital_Wallets_Locations
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use MyCLabs\Enum\Enum;

/**
 * Possible locations for digital wallets to show on.
 */
class Digital_Wallets_Locations extends Enum {
	const CART         = 'cart';
	const CHECKOUT     = 'checkout';
	const PRODUCT_PAGE = 'product';
}
