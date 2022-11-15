<?php
/**
 * Class Immutable_Param
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Core\Exceptions;

use WCPay\Exceptions\Base_Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when someone tries to change immutable param.
 */
class Immutable_Parameter_Exception extends Base_Exception {
}
