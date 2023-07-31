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
 * This class gives a list of all Payment Intent status name constants.
 * ref: https://stripe.com/docs/api/payment_intents/object#payment_intent_object-status
 *
 * @psalm-immutable
 */
class Payment_Intent_Status extends Base_Constant {
	const REQUIRES_PAYMENT_METHOD = 'requires_payment_method';
	const REQUIRES_CONFIRMATION   = 'requires_confirmation';
	const REQUIRES_ACTION         = 'requires_action';
	const PROCESSING              = 'processing';
	const REQUIRES_CAPTURE        = 'requires_capture';
	const CANCELED                = 'canceled';
	const SUCCEEDED               = 'succeeded';
}
