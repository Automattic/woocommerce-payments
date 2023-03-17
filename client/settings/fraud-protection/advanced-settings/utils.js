/**
 * External dependencies
 */
import { sprintf } from '@wordpress/i18n';
import { CheckOperators, Checks, Outcomes, Rules } from './constants';

const getRuleBase = ( setting, block ) => {
	return {
		key: setting,
		outcome: block ? Outcomes.BLOCK : Outcomes.REVIEW,
		check: null,
	};
};

const buildRuleset = ( ruleKey, shouldBlock, ruleConfiguration = {} ) => {
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
			if (
				parseInt( ruleConfiguration.min_items, 10 ) &&
				parseInt( ruleConfiguration.max_items, 10 )
			) {
				ruleBase.check = {
					operator: CheckOperators.LIST_OPERATOR_OR,
					checks: [
						{
							key: Checks.CHECK_ITEM_COUNT,
							operator: CheckOperators.OPERATOR_LT,
							value:
								parseInt( ruleConfiguration.min_items, 10 ) ??
								null,
						},
						{
							key: Checks.CHECK_ITEM_COUNT,
							operator: CheckOperators.OPERATOR_GT,
							value:
								parseInt( ruleConfiguration.max_items, 10 ) ??
								null,
						},
					],
				};
			} else {
				ruleBase.check = parseInt( ruleConfiguration.min_items, 10 )
					? {
							key: Checks.CHECK_ITEM_COUNT,
							operator: CheckOperators.OPERATOR_LT,
							value:
								parseInt( ruleConfiguration.min_items, 10 ) ??
								null,
					  }
					: {
							key: Checks.CHECK_ITEM_COUNT,
							operator: CheckOperators.OPERATOR_GT,
							value:
								parseInt( ruleConfiguration.max_items, 10 ) ??
								null,
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
				value: parseInt( ruleConfiguration.max_orders, 10 ),
			};
			break;
		case Rules.RULE_PURCHASE_PRICE_THRESHOLD:
			if (
				parseFloat( ruleConfiguration.min_amount ) &&
				parseFloat( ruleConfiguration.max_amount )
			) {
				ruleBase.check = {
					operator: CheckOperators.LIST_OPERATOR_OR,
					checks: [
						{
							key: Checks.CHECK_ORDER_TOTAL,
							operator: CheckOperators.OPERATOR_LT,
							value: parseFloat( ruleConfiguration.min_amount ),
						},
						{
							key: Checks.CHECK_ORDER_TOTAL,
							operator: CheckOperators.OPERATOR_GT,
							value: parseFloat( ruleConfiguration.max_amount ),
						},
					],
				};
			} else {
				ruleBase.check = parseFloat( ruleConfiguration.min_amount )
					? {
							key: Checks.CHECK_ORDER_TOTAL,
							operator: CheckOperators.OPERATOR_LT,
							value: parseFloat( ruleConfiguration.min_amount ),
					  }
					: {
							key: Checks.CHECK_ORDER_TOTAL,
							operator: CheckOperators.OPERATOR_GT,
							value: parseFloat( ruleConfiguration.max_amount ),
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

	return rulesetConfig.filter( ( rule ) => rule );
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
			interval: 24,
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
				const minItems = findCheck(
					rule.check,
					Checks.CHECK_ITEM_COUNT,
					CheckOperators.OPERATOR_LT
				);
				const maxItems = findCheck(
					rule.check,
					Checks.CHECK_ITEM_COUNT,
					CheckOperators.OPERATOR_GT
				);
				parsedUIConfig[ rule.key ] = {
					enabled: true,
					block: rule.outcome === Outcomes.BLOCK,
					min_items: minItems ? minItems.value : '',
					max_items: maxItems ? maxItems.value : '',
				};
				break;
			case Rules.RULE_ORDER_VELOCITY:
				parsedUIConfig[ rule.key ] = {
					enabled: true,
					block: rule.outcome === Outcomes.BLOCK,
					max_orders: rule.check.value,
					interval: parseInt(
						rule.check.key.match( /^orders_since_(\d+)h$/ )[ 1 ],
						10
					),
				};
				break;
			case Rules.RULE_PURCHASE_PRICE_THRESHOLD:
				const minAmount = findCheck(
					rule.check,
					Checks.CHECK_ORDER_TOTAL,
					CheckOperators.OPERATOR_LT
				);
				const maxAmount = findCheck(
					rule.check,
					Checks.CHECK_ORDER_TOTAL,
					CheckOperators.OPERATOR_GT
				);
				parsedUIConfig[ rule.key ] = {
					enabled: true,
					block: rule.outcome === Outcomes.BLOCK,
					min_amount: minAmount ? minAmount.value : '',
					max_amount: maxAmount ? maxAmount.value : '',
				};
				break;
		}
	}

	return Object.assign( {}, defaultUIConfig, parsedUIConfig );
};
