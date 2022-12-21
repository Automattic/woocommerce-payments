<?php
/**
 * Class Order_Statuses
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use MyCLabs\Enum\Enum;

/**
 * This class gives a list of all the possible order status constants.
 *
 * @psalm-immutable
 */
class Order_Statuses extends Enum {
	const STATUS_CANCELLED = 'cancelled';
	const STATUS_COMPLETED = 'completed';
	const STATUS_FAILED    = 'failed';
	const STATUS_ON_HOLD   = 'on-hold';
	const STATUS_PENDING   = 'pending';
}
