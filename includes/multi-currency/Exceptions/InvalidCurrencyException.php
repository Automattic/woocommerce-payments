<?php
/**
 * Class InvalidCurrencyException
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\MultiCurrency\Exceptions;

use WCPay\Exceptions\Base_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing errors when an invalid currency is used.
 */
class InvalidCurrencyException extends Base_Exception {}
