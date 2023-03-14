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

use MyCLabs\Enum\Enum;

/**
 * This class gives a list of all the possible fraud outcome status constants.
 *
 * @psalm-immutable
 */
class Fraud_Outcome_Status extends Enum {
	const APPROVE         = 'approve';
	const REVIEW          = 'review';
	const REVIEW_APPROVED = 'review_approved';
	const BLOCK           = 'block';
	const NOT_WCPAY       = 'not_wcpay';
}
