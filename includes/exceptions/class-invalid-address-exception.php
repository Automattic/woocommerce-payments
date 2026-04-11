<?php
/**
 * Class Invalid_Address_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing errors when address is invalid.
 */
class Invalid_Address_Exception extends Exception {}
