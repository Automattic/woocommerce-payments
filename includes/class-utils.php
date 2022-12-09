<?php
/**
 * Class Logger
 *
 * @package WooCommerce\Payments
 */

namespace WCPay;

/**
 * Mockable time function
 *
 * @return int The mocked or original time.
 */
function time() {
	return apply_filters( 'wcpay_mock_time', \time() );
}
