<?php
/**
 * Class WC_Payments_AddPaymentMethod_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when we have issues with creating
 * payment methods, e.g. invalid requests or errors on the server side.
 */
class WC_Payments_AddPaymentMethod_Exception extends WC_Payments_Base_Exception {
}
