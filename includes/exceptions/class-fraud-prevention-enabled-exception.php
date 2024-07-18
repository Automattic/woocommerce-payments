<?php
/**
 * Class Fraud_Prevention_Enabled_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when fraud prevension service is enabled.
 */
class Fraud_Prevention_Enabled_Exception extends Process_Payment_Exception {
}
