/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	Outcomes,
	Rules,
} from '../../settings/fraud-protection/advanced-settings/constants';

export const fraudOutcomeRulesetMapping = {
	[ Outcomes.REVIEW ]: {
		[ Rules.RULE_AVS_VERIFICATION ]: __(
			'Place in review if the AVS verification fails',
			'woocommerce-payments'
		),
		[ Rules.RULE_ADDRESS_MISMATCH ]: __(
			'Place in review if the shipping address country differs from the billing address country',
			'woocommerce-payments'
		),
		[ Rules.RULE_INTERNATIONAL_IP_ADDRESS ]: __(
			'Place in review if the country resolved from customer IP is not listed in your selling countries',
			'woocommerce-payments'
		),
		[ Rules.RULE_IP_ADDRESS_MISMATCH ]: __(
			'Place in review if the order originates from a country different from the shipping address country',
			'woocommerce-payments'
		),
		[ Rules.RULE_ORDER_ITEMS_THRESHOLD ]: __(
			'Place in review if the items count is not in your defined range',
			'woocommerce-payments'
		),
		[ Rules.RULE_PURCHASE_PRICE_THRESHOLD ]: __(
			'Place in review if the purchase price is not in your defined range',
			'woocommerce-payments'
		),
	},
	[ Outcomes.BLOCK ]: {
		[ Rules.RULE_AVS_VERIFICATION ]: __(
			'Block if the AVS verification fails',
			'woocommerce-payments'
		),
		[ Rules.RULE_ADDRESS_MISMATCH ]: __(
			'Block if the shipping address differs from the billing address',
			'woocommerce-payments'
		),
		[ Rules.RULE_INTERNATIONAL_IP_ADDRESS ]: __(
			'Block if the country resolved from customer IP is not listed in your selling countries',
			'woocommerce-payments'
		),
		[ Rules.RULE_IP_ADDRESS_MISMATCH ]: __(
			'Block if the order originates from a country different from the shipping address country',
			'woocommerce-payments'
		),
		[ Rules.RULE_ORDER_ITEMS_THRESHOLD ]: __(
			'Block if the items count is not in your defined range',
			'woocommerce-payments'
		),
		[ Rules.RULE_PURCHASE_PRICE_THRESHOLD ]: __(
			'Block if the purchase price is not in your defined range',
			'woocommerce-payments'
		),
	},
};

// eslint-disable-next-line @typescript-eslint/naming-convention
export const paymentFailureMapping = {
	card_declined: __(
		'The card was declined by the bank',
		'woocommerce-payments'
	),
	expired_card: __( 'The card has expired', 'woocommerce-payments' ),
	incorrect_cvc: __(
		'The security code is incorrect',
		'woocommerce-payments'
	),
	incorrect_number: __(
		'The card number is incorrect',
		'woocommerce-payments'
	),
	incorrect_zip: __( 'The postal code is incorrect', 'woocommerce-payments' ),
	invalid_cvc: __( 'The security code is invalid', 'woocommerce-payments' ),
	invalid_expiry_month: __(
		'The expiration month is invalid',
		'woocommerce-payments'
	),
	invalid_expiry_year: __(
		'The expiration year is invalid',
		'woocommerce-payments'
	),
	invalid_number: __( 'The card number is invalid', 'woocommerce-payments' ),
	processing_error: __(
		'An error occurred while processing the card',
		'woocommerce-payments'
	),
	authentication_required: __(
		'The payment requires authentication',
		'woocommerce-payments'
	),
	insufficient_funds: __(
		'The card has insufficient funds to complete the purchase',
		'woocommerce-payments'
	),

	// Default fallback
	default: __( 'The payment was declined', 'woocommerce-payments' ),
};
