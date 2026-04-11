<?php
/**
 * Class Invalid_Phone_Number_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when phone number is invalid.
 */
class Invalid_Phone_Number_Exception extends Process_Payment_Exception {
}
