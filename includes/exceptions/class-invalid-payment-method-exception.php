<?php
/**
 * Class Invalid_Payment_Method_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when payment method can not be identified or used.
 */
class Invalid_Payment_Method_Exception extends Base_Exception {
}
