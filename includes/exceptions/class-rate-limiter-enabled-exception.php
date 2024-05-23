<?php
/**
 * Class Rate_Limiter_Enabled_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when rate limiter is enabled.
 */
class Rate_Limiter_Enabled_Exception extends Process_Payment_Exception {
}
