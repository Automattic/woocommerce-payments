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
