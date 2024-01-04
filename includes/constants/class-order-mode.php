<?php
/**
 * Class Order_Mode
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Enum saved in the order meta for the mode that the payment was processed in.
 *
 * @psalm-immutable
 */
class Order_Mode extends Base_Constant {
	const TEST       = 'test';
	const PRODUCTION = 'prod';
}
