<?php
/**
 * Class Payment_Information
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Enum used in WCPay\Payment_Information to determine whether a payment was
 * initated by a merchant or a customer.
 *
 * @psalm-immutable
 */
class Payment_Initiated_By extends Base_Constant {
	const MERCHANT = 'initiated_by_merchant';
	const CUSTOMER = 'initiated_by_customer';
}
