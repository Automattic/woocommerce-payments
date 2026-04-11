<?php
/**
 * WCPay container loader
 *
 * @package WooCommerce\Payments
 */

use WCPay\Container;

/**
 * Returns the WCPay DI container.
 *
 * @return Container
 */
function wcpay_get_container() {
	if ( ! isset( $GLOBALS['wcpay_container'] ) ) {
		$GLOBALS['wcpay_container'] = new Container();
	}

	return $GLOBALS['wcpay_container'];
}
