<?php
/**
 * Class Invalid_Webhook_Data_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when webhook data is invalid.
 */
class Invalid_Webhook_Data_Exception extends Exception {
}
