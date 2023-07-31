<?php
/**
 * Class Invalid_Request_Parameter_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\Exceptions\Server\Request;

use WCPay\Exceptions\Base_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when immutable parameter inside request class is changed.
 */
class Invalid_Request_Parameter_Exception extends Base_Exception {
}
