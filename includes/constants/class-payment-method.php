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

/**
 * Possible enum values for the type of the PaymentMethod.
 * https://stripe.com/docs/api/payment_methods/object#payment_method_object-type
 *
 * @psalm-immutable
 */
class Payment_Method extends Base_Constant {
	const BANCONTACT      = 'bancontact';
	const BASC            = 'bacs_debit';
	const BECS            = 'au_becs_debit';
	const CARD            = 'card';
	const CARD_PRESENT    = 'card_present';
	const EPS             = 'eps';
	const GIROPAY         = 'giropay';
	const IDEAL           = 'ideal';
	const INTERAC_PRESENT = 'interac_present';
	const LINK            = 'link';
	const P24             = 'p24';
	const SEPA            = 'sepa_debit';
	const SOFORT          = 'sofort';
	const US_BANK_ACCOUNT = 'us_bank_account';
	const AFFIRM          = 'affirm';
	const AFTERPAY        = 'afterpay_clearpay';

	const IPP_ALLOWED_PAYMENT_METHODS = [
		self::CARD_PRESENT,
		self::INTERAC_PRESENT,
	];
}
