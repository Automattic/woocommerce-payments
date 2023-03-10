/** @format */

/**
 * External dependencies
 */
import { sprintf } from '@wordpress/i18n';

export const Outcomes = {
	BLOCK: 'block',
	REVIEW: 'review',
	ALLOW: 'allow',
};

export const Rules = {
	RULE_AVS_MISMATCH: 'avs_mismatch',
	RULE_CVC_VERIFICATION: 'cvc_verification',
	RULE_ADDRESS_MISMATCH: 'address_mismatch',
	RULE_INTERNATIONAL_IP_ADDRESS: 'international_ip_address',
	RULE_INTERNATIONAL_BILLING_ADDRESS: 'international_billing_address',
	RULE_ORDER_VELOCITY: 'order_velocity',
	RULE_ORDER_ITEMS_THRESHOLD: 'order_items_threshold',
	RULE_PURCHASE_PRICE_THRESHOLD: 'purchase_price_threshold',
};

export const Checks = {
	CHECK_AVS_CHECK: 'avs_check',
	CHECK_CVC_CHECK: 'cvc_check',
	CHECK_BILLING_SHIPPING_ADDRESS_SAME: 'billing_shipping_address_same',
	CHECK_IP_COUNTRY_SAME_WITH_ACCOUNT_COUNTRY:
		'ip_country_same_with_account_country',
	CHECK_BILLING_COUNTRY_SAME_WITH_ACCOUNT_COUNTRY:
		'billing_country_same_with_account_country',
	CHECK_MAX_ORDERS: 'max_orders',
	CHECK_ORDERS_SINCE_H: 'orders_since_%dh',
	CHECK_ITEM_COUNT: 'item_count',
	CHECK_ORDER_TOTAL: 'order_total',
};

export const CheckOperators = {
	LIST_OPERATOR_AND: 'and',
	LIST_OPERATOR_OR: 'or',
	OPERATOR_EQUALS: 'equals',
	OPERATOR_NOT_EQUALS: 'not_equals',
	OPERATOR_GTE: 'greater_or_equal',
	OPERATOR_GT: 'greater_than',
	OPERATOR_LTE: 'less_or_equal',
	OPERATOR_LT: 'less_than',
};

const getRuleBase = ( setting, block ) => {
	return {
		key: setting,
		outcome: block ? Outcomes.BLOCK : Outcomes.REVIEW,
		check: null,
	};
};

export const buildRuleset = (
	ruleKey,
	shouldBlock,
	ruleConfiguration = {}
) => {
	const ruleBase = getRuleBase( ruleKey, shouldBlock );
	switch ( ruleKey ) {
		case Rules.RULE_AVS_MISMATCH:
			ruleBase.check = {
				key: Checks.CHECK_AVS_CHECK,
				operator: CheckOperators.OPERATOR_EQUALS,
				value: false,
			};
			break;
		case Rules.RULE_ADDRESS_MISMATCH:
			ruleBase.check = {
				key: Checks.CHECK_BILLING_SHIPPING_ADDRESS_SAME,
				operator: CheckOperators.OPERATOR_EQUALS,
				value: false,
			};
			break;
		case Rules.RULE_CVC_VERIFICATION:
			ruleBase.check = {
				key: Checks.CHECK_CVC_CHECK,
				operator: CheckOperators.OPERATOR_EQUALS,
				value: false,
			};
			break;
		case Rules.RULE_INTERNATIONAL_IP_ADDRESS:
			ruleBase.check = {
				key: Checks.CHECK_IP_COUNTRY_SAME_WITH_ACCOUNT_COUNTRY,
				operator: CheckOperators.OPERATOR_EQUALS,
				value: false,
			};
			break;
		case Rules.RULE_INTERNATIONAL_BILLING_ADDRESS:
			ruleBase.check = {
				key: Checks.CHECK_BILLING_COUNTRY_SAME_WITH_ACCOUNT_COUNTRY,
				operator: CheckOperators.OPERATOR_EQUALS,
				value: false,
			};
			break;
		case Rules.RULE_ORDER_ITEMS_THRESHOLD:
			if ( ruleConfiguration.min_items && ruleConfiguration.max_items ) {
				ruleBase.check = {
					operator: CheckOperators.LIST_OPERATOR_OR,
					checks: [
						{
							key: Checks.CHECK_ITEM_COUNT,
							operator: CheckOperators.OPERATOR_LT,
							value: ruleConfiguration.min_items,
						},
						{
							key: Checks.CHECK_ITEM_COUNT,
							operator: CheckOperators.OPERATOR_GT,
							value: ruleConfiguration.max_items,
						},
					],
				};
			} else {
				ruleBase.check = ruleConfiguration.min_items
					? {
							key: Checks.CHECK_ITEM_COUNT,
							operator: CheckOperators.OPERATOR_LT,
							value: ruleConfiguration.min_items,
					  }
					: {
							key: Checks.CHECK_ITEM_COUNT,
							operator: CheckOperators.OPERATOR_GT,
							value: ruleConfiguration.max_items,
					  };
			}
			break;
		case Rules.RULE_ORDER_VELOCITY:
			ruleBase.check = {
				key: sprintf(
					Checks.CHECK_ORDERS_SINCE_H,
					ruleConfiguration.interval
				),
				operator: CheckOperators.OPERATOR_GT,
				value: ruleConfiguration.max_orders,
			};
			break;
		case Rules.RULE_PURCHASE_PRICE_THRESHOLD:
			if (
				ruleConfiguration.min_amount &&
				ruleConfiguration.max_amount
			) {
				ruleBase.check = {
					operator: CheckOperators.LIST_OPERATOR_OR,
					checks: [
						{
							key: Checks.CHECK_ORDER_TOTAL,
							operator: CheckOperators.OPERATOR_LT,
							value: ruleConfiguration.min_amount,
						},
						{
							key: Checks.CHECK_ORDER_TOTAL,
							operator: CheckOperators.OPERATOR_GT,
							value: ruleConfiguration.max_amount,
						},
					],
				};
			} else {
				ruleBase.check = ruleConfiguration.min_amount
					? {
							key: Checks.CHECK_ORDER_TOTAL,
							operator: CheckOperators.OPERATOR_LT,
							value: ruleConfiguration.min_amount,
					  }
					: {
							key: Checks.CHECK_ORDER_TOTAL,
							operator: CheckOperators.OPERATOR_GT,
							value: ruleConfiguration.max_amount,
					  };
			}
			break;
	}
	return ruleBase;
};

const findCheck = ( current, checkKey, operator ) => {
	if ( checkKey === current.key && operator === current.operator ) {
		return current;
	}
	if ( current.checks ) {
		for ( const i in current.checks ) {
			const check = current.checks[ i ];
			const result = findCheck( check, checkKey, operator );
			if ( false !== result ) {
				return result;
			}
		}
	}
	return false;
};

export const writeRuleset = ( config ) => {
	const rulesetConfig = [];
	for ( const key in config ) {
		if ( config[ key ].enabled ) {
			rulesetConfig.push(
				buildRuleset( key, config[ key ].block, config[ key ] )
			);
		}
	}
	return rulesetConfig;
};

export const readRuleset = ( rulesetConfig ) => {
	const defaultUIConfig = {
		[ Rules.RULE_AVS_MISMATCH ]: { enabled: false, block: false },
		[ Rules.RULE_CVC_VERIFICATION ]: { enabled: false, block: false },
		[ Rules.RULE_ADDRESS_MISMATCH ]: { enabled: false, block: false },
		[ Rules.RULE_INTERNATIONAL_IP_ADDRESS ]: {
			enabled: false,
			block: false,
		},
		[ Rules.RULE_INTERNATIONAL_BILLING_ADDRESS ]: {
			enabled: false,
			block: false,
		},
		[ Rules.RULE_ORDER_VELOCITY ]: {
			enabled: false,
			block: false,
			max_orders: 0,
			interval: 72,
		},
		[ Rules.RULE_ORDER_ITEMS_THRESHOLD ]: {
			enabled: false,
			block: false,
			min_items: null,
			max_items: null,
		},
		[ Rules.RULE_PURCHASE_PRICE_THRESHOLD ]: {
			enabled: false,
			block: false,
			min_amount: null,
			max_amount: null,
		},
	};
	const parsedUIConfig = {};
	for ( const id in rulesetConfig ) {
		const rule = rulesetConfig[ id ];
		switch ( rule.key ) {
			case Rules.RULE_AVS_MISMATCH:
				parsedUIConfig[ rule.key ] = {
					enabled: true,
					block: rule.outcome === Outcomes.BLOCK,
				};
				break;
			case Rules.RULE_ADDRESS_MISMATCH:
				parsedUIConfig[ rule.key ] = {
					enabled: true,
					block: rule.outcome === Outcomes.BLOCK,
				};
				break;
			case Rules.RULE_CVC_VERIFICATION:
				parsedUIConfig[ rule.key ] = {
					enabled: true,
					block: rule.outcome === Outcomes.BLOCK,
				};
				break;
			case Rules.RULE_INTERNATIONAL_IP_ADDRESS:
				parsedUIConfig[ rule.key ] = {
					enabled: true,
					block: rule.outcome === Outcomes.BLOCK,
				};
				break;
			case Rules.RULE_INTERNATIONAL_BILLING_ADDRESS:
				parsedUIConfig[ rule.key ] = {
					enabled: true,
					block: rule.outcome === Outcomes.BLOCK,
				};
				break;
			case Rules.RULE_ORDER_ITEMS_THRESHOLD:
				parsedUIConfig[ rule.key ] = {
					enabled: true,
					block: rule.outcome === Outcomes.BLOCK,
					min_items: findCheck(
						rule.check,
						Checks.CHECK_ITEM_COUNT,
						CheckOperators.OPERATOR_LT
					).value,
					max_items: findCheck(
						rule.check,
						Checks.CHECK_ITEM_COUNT,
						CheckOperators.OPERATOR_GT
					).value,
				};
				break;
			case Rules.RULE_ORDER_VELOCITY:
				parsedUIConfig[ rule.key ] = {
					enabled: true,
					block: rule.outcome === Outcomes.BLOCK,
					max_orders: rule.check.value,
					interval: rule.check.key.match(
						/^orders_since_(\d+)h$/
					)[ 1 ],
				};
				break;
			case Rules.RULE_PURCHASE_PRICE_THRESHOLD:
				parsedUIConfig[ rule.key ] = {
					enabled: true,
					block: rule.outcome === Outcomes.BLOCK,
					min_amount: findCheck(
						rule.check,
						Checks.CHECK_ORDER_TOTAL,
						CheckOperators.OPERATOR_LT
					).value,
					max_amount: findCheck(
						rule.check,
						Checks.CHECK_ORDER_TOTAL,
						CheckOperators.OPERATOR_GT
					).value,
				};
				break;
		}
	}
	return Object.assign( {}, defaultUIConfig, parsedUIConfig );
};
