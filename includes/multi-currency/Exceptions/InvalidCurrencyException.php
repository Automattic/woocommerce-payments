<?php
/**
 * Class Invalid_Currency_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\MultiCurrency\Exceptions;

use WCPay\Exceptions\Base_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing errors when an invalid currency is used.
 */
class Invalid_Currency_Exception extends Base_Exception {}
