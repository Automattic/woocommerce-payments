<?php
/**
 * Class Payment_Type
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use MyCLabs\Enum\Enum;

/**
 * Enum used in WCPay\Payment_Information to determine whether a payment
 * is a single payment or a part of a recurring series.
 *
 * @psalm-immutable
 */
class Payment_Type extends Enum {
	const SINGLE    = 'single';
	const RECURRING = 'recurring';
}
