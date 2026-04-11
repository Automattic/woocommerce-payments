<?php
/**
 * Class Order_ID_Mismatch_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when payment processing is not possible or fails.
 */
class Order_ID_Mismatch_Exception extends Base_Exception {
}
