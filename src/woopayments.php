<?php
/**
 * WooPayments src root file.
 *
 * @package WooPayments
 */

use WooPayments\Container;

/**
 * Returns the WooPayments DI container.
 *
 * @return Container
 */
function wcpay_get_container() {
	if ( ! isset( $GLOBALS['woopayments_container'] ) ) {
		$GLOBALS['woopayments_container'] = new Container();
	}

	return $GLOBALS['woopayments_container'];
}
