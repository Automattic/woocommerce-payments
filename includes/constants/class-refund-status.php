<?php
/**
 * Class Refund_Status
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * This class gives a list of all the possible WCPay refund status constants.
 *
 * @psalm-immutable
 */
class Refund_Status extends Base_Constant {
	const PENDING   = 'pending';
	const SUCCEEDED = 'succeeded';
	const FAILED    = 'failed';
	const CANCELED  = 'canceled';
}
