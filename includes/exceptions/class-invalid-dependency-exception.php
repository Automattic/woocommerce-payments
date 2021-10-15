<?php
/**
 * Class Rest_Request_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing errors in the plugin dependency check service
 */
class Invalid_Dependency_Exception extends Exception {}
