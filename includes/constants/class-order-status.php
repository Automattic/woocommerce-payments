<?php
/**
 * Class Order_Status
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * This class gives a list of all the possible order status constants.
 *
 * @psalm-immutable
 */
class Order_Status extends Base_Constant {
	const CANCELLED  = 'cancelled';
	const COMPLETED  = 'completed';
	const FAILED     = 'failed';
	const ON_HOLD    = 'on-hold';
	const PENDING    = 'pending';
	const PROCESSING = 'processing';
	const REFUNDED   = 'refunded';
	const TRASH      = 'trash';
}
