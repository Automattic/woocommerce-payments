<?php
/**
 * Class Immutable_Param
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\Exceptions\Server\Request;

use WCPay\Exceptions\Base_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when someone tries to change an immutable param.
 */
class Immutable_Parameter_Exception extends Base_Exception {
}
