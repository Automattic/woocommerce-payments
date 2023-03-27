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
 * This class gives a list of all the possible fraud meta mbox type constants.
 *
 * @psalm-immutable
 */
class Fraud_Meta_Box_Type extends Base_Constant {
	const ALLOW          = 'allow';
	const REVIEW         = 'review';
	const REVIEW_ALLOWED = 'review_allowed';
	const BLOCK          = 'block';
	const NOT_WCPAY      = 'not_wcpay';
}
