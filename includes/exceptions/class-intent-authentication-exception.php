<?php
/**
 * Class Intent_Authentication_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when there's a problem updating an order after a payment
 * authentication attempt was made by the customer, e.g. for 3DS authentication.
 */
class Intent_Authentication_Exception extends Base_Exception {
}
