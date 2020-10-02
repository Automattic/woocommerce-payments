<?php
/**
 * Class Add_Payment_Method_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when we have issues with creating
 * payment methods, e.g. invalid requests or errors on the server side.
 */
class Add_Payment_Method_Exception extends Base_Exception {
}
