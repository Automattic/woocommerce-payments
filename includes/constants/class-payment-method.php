<?php
/**
 * Class Payment_Method
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use MyCLabs\Enum\Enum;

/**
 * Possible enum values for the type of the PaymentMethod.
 * https://stripe.com/docs/api/payment_methods/object#payment_method_object-type
 *
 * @psalm-immutable
 */
class Payment_Method extends Enum {
	const CARD         = 'card';
	const SEPA         = 'sepa_debit';
	const CARD_PRESENT = 'card_present';
}
