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

/**
 * Enum used in WCPay\Payment_Information to determine whether a payment
 * is a single payment or a part of a recurring series.
 *
 * @method static Payment_Type SINGLE()
 * @method static Payment_Type RECURRING()
 *
 * @phpstan-immutable
 */
class Payment_Type extends Base_Constant {
	const SINGLE    = 'single';
	const RECURRING = 'recurring';
}
