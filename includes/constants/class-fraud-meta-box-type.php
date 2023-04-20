<?php
/**
 * Class Fraud_Meta_Box_Type
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * This class gives a list of all the possible fraud meta box type constants.
 *
 * @psalm-immutable
 */
class Fraud_Meta_Box_Type extends Base_Constant {
	const ALLOW            = 'allow';
	const BLOCK            = 'block';
	const NOT_CARD         = 'not_card';
	const NOT_WCPAY        = 'not_wcpay';
	const PAYMENT_STARTED  = 'payment_started';
	const REVIEW           = 'review';
	const REVIEW_ALLOWED   = 'review_allowed';
	const REVIEW_BLOCKED   = 'review_blocked';
	const REVIEW_EXPIRED   = 'review_expired';
	const REVIEW_FAILED    = 'review_failed';
	const TERMINAL_PAYMENT = 'terminal_payment';
}
