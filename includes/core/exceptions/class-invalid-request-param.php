<?php
/**
 * Class Invalid_Request_Param
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\Exceptions;

use WCPay\Exceptions\Base_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when payment method can not be identified or used.
 */
class Invalid_Request_Param extends Base_Exception {
}
