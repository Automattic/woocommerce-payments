<?php
/**
 * WooPayments src root file.
 *
 * @package WooPayments
 */

use WooPayments\Container;

$GLOBALS['woopayments_container'] = new Container();

/**
 * Returns the WooPayments DI container.
 *
 * @return Container
 */
function wcpay_get_container() {
	return $GLOBALS['woopayments_container'];
}
