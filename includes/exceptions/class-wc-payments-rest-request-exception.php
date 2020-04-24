<?php
/**
 * Class WC_Payments_Rest_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing errors in REST API controllers (e.g. issues with missing parameters in requests).
 */
class WC_Payments_Rest_Request_Exception extends Exception {}
