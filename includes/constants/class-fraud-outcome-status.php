<?php
/**
 * Class Fraud_Outcome_Status
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * This class gives a list of all the possible fraud outcome status constants.
 *
 * @psalm-immutable
 */
class Fraud_Outcome_Status extends Base_Constant {
	const ALLOW          = 'allow';
	const REVIEW         = 'review';
	const REVIEW_ALLOWED = 'review_allowed';
	const BLOCK          = 'block';
	const NOT_WCPAY      = 'not_wcpay';
}
