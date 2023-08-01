<?php
/**
 * WCPay container loader
 *
 * @package WooCommerce\Payments
 */

use WCPay\Container;

$GLOBALS['wcpay_container'] = new Container();

/**
 * Returns the WCPay DI container.
 *
 * @return Container
 */
function wcpay_get_container() {
	return $GLOBALS['wcpay_container'];
}
