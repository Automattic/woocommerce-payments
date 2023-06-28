<?php
/**
 * Class InvalidCurrencyRateException
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\MultiCurrency\Exceptions;

use WCPay\Exceptions\Base_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing errors when an invalid currency rate is used.
 */
class InvalidCurrencyRateException extends Base_Exception {}
