<?php
/**
 * Class Order_Not_Found_Exception
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Exception for throwing an error when we have issues when trying to find/use an order.
 */
class Order_Not_Found_Exception extends Base_Exception {}
