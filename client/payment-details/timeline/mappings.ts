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
		[ Rules.RULE_ADDRESS_MISMATCH ]: __(
			'Place in review if the shipping address differs from the billing address',
			'woocommerce-payments'
		),
		[ Rules.RULE_INTERNATIONAL_IP_ADDRESS ]: __(
			'Place in review if the billing address is outside your supported countries',
			'woocommerce-payments'
		),
		[ Rules.RULE_INTERNATIONAL_BILLING_ADDRESS ]: __(
			'Place in review if the shipping address differs from the billing address',
			'woocommerce-payments'
		),
		[ Rules.RULE_ORDER_ITEMS_THRESHOLD ]: __(
			'Place in review if the shipping address differs from the billing address',
			'woocommerce-payments'
		),
		[ Rules.RULE_PURCHASE_PRICE_THRESHOLD ]: __(
			'Place in review if the shipping address differs from the billing address',
			'woocommerce-payments'
		),
	},
	[ Outcomes.BLOCK ]: {
		[ Rules.RULE_ADDRESS_MISMATCH ]: __(
			'Block if the shipping address differs from the billing address',
			'woocommerce-payments'
		),
		[ Rules.RULE_INTERNATIONAL_IP_ADDRESS ]: __(
			'Block if the billing address is outside your supported countries',
			'woocommerce-payments'
		),
		[ Rules.RULE_INTERNATIONAL_BILLING_ADDRESS ]: __(
			'Block if the shipping address differs from the billing address',
			'woocommerce-payments'
		),
		[ Rules.RULE_ORDER_ITEMS_THRESHOLD ]: __(
			'Block if the shipping address differs from the billing address',
			'woocommerce-payments'
		),
		[ Rules.RULE_PURCHASE_PRICE_THRESHOLD ]: __(
			'Block if the shipping address differs from the billing address',
			'woocommerce-payments'
		),
	},
};
