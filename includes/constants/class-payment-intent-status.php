<?php
/**
 * Class Payment_Intent_Status
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Payment_Intent_Status was deprecated in favor of Intent_Status.
 *
 * @deprecated 6.4.0
 * @see Intent_Status
 *
 * @psalm-immutable
 */
class Payment_Intent_Status extends Intent_Status {

}
