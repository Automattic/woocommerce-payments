<?php
/**
 * Class Setup_Intent_Status
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * This class gives a list of all Setup Intent status name constants.
 * ref: https://stripe.com/docs/api/setup_intents/object#setup_intent_object-status
 *
 * @psalm-immutable
 */
class Setup_Intent_Status extends Base_Constant {
	const REQUIRES_PAYMENT_METHOD = 'requires_payment_method';
	const REQUIRES_CONFIRMATION   = 'requires_confirmation';
	const REQUIRES_ACTION         = 'requires_action';
	const PROCESSING              = 'processing';
	const CANCELED                = 'canceled';
	const SUCCEEDED               = 'succeeded';
}
