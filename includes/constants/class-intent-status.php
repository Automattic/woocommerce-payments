<?php
/**
 * Class Intent_Status
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * This class gives a list of all Payment and Setup Intent status name constants.
 * - https://stripe.com/docs/api/payment_intents/object#payment_intent_object-status
 * - https://stripe.com/docs/api/setup_intents/object#setup_intent_object-status
 *
 * @psalm-immutable
 */
class Intent_Status extends Base_Constant {
	const REQUIRES_PAYMENT_METHOD = 'requires_payment_method';
	const REQUIRES_CONFIRMATION   = 'requires_confirmation';
	const REQUIRES_ACTION         = 'requires_action';
	const PROCESSING              = 'processing';
	const REQUIRES_CAPTURE        = 'requires_capture';
	const CANCELED                = 'canceled';
	const SUCCEEDED               = 'succeeded';

	/**
	 * Stripe intents that are treated as successfully created, i.e. authorized.
	 *
	 * @type array
	 */
	const AUTHORIZED_STATUSES = [
		self::SUCCEEDED,
		self::REQUIRES_CAPTURE,
		self::PROCESSING,
	];
}
