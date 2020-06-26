<?php
/**
 * Class WC_Payments_Authentication_Intent_Mismatch_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when payment intents don't match during 3DS authentication.
 */
class WC_Payments_Authentication_Intent_Mismatch_Exception extends Exception {}
