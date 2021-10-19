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
class Invalid_Dependency_Exception extends Exception {

	const WOOCORE_NOT_FOUND     = 'woocore_disabled';
	const WOOCORE_INCOMPATIBLE  = 'woocore_outdated';
	const WOOADMIN_NOT_FOUND    = 'wc_admin_not_found';
	const WOOADMIN_INCOMPATIBLE = 'wc_admin_outdated';
	const WP_INCOMPATIBLE       = 'wp_outdated';
}
