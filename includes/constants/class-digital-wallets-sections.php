<?php
/**
 * Class Capture_Type
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use MyCLabs\Enum\Enum;

/**
 * Possible sections for digital wallets to show on.
 */
class Digital_Wallets_Sections extends Enum {
	const CART         = 'cart';
	const CHECKOUT     = 'checkout';
	const PRODUCT_PAGE = 'product_page';
}
