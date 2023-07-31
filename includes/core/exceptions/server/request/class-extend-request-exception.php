<?php
/**
 * Class Extend_Request_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\Exceptions\Server\Request;

use WCPay\Exceptions\Base_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when a request object is extended in wrong way.
 */
class Extend_Request_Exception extends Base_Exception {
}
