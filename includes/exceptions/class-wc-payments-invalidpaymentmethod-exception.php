<?php
/**
 * Class WC_Payments_InvalidPaymentMethod_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when payment method can not be identified or used.
 */
class WC_Payments_InvalidPaymentMethod_Exception extends WC_Payments_Base_Exception {
}
