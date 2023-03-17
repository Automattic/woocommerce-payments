/* @format */

/**
 * External dependencies
 */
import { sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { CheckOperators, Checks, Outcomes, Rules } from '../constants';
import { readRuleset, writeRuleset } from '../utils';
const defaultUIConfig = {
	address_mismatch: {
		block: false,
		enabled: false,
	},
	avs_mismatch: {
		block: false,
		enabled: false,
	},
	cvc_verification: {
		block: false,
		enabled: false,
	},
	international_billing_address: {
		block: false,
		enabled: false,
	},
	international_ip_address: {
		block: false,
		enabled: false,
	},
	order_items_threshold: {
		block: false,
		enabled: false,
		max_items: null,
		min_items: null,
	},
	order_velocity: {
		block: false,
		enabled: false,
		interval: 24,
		max_orders: 0,
	},
	purchase_price_threshold: {
		block: false,
		enabled: false,
		max_amount: null,
		min_amount: null,
	},
};
describe( 'Ruleset adapter utilities test', () => {
	test( 'converts an empty ruleset to default UI config', () => {
		const ruleset = [];
		const expected = defaultUIConfig;
		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'converts an address mismatch ruleset to matching UI config', () => {
		const ruleset = [
			{
				key: Rules.RULE_ADDRESS_MISMATCH,
				outcome: Outcomes.REVIEW,
				check: {
					key: Checks.CHECK_BILLING_SHIPPING_ADDRESS_SAME,
					operator: CheckOperators.OPERATOR_EQUALS,
					value: false,
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			address_mismatch: {
				block: false,
				enabled: true,
			},
		} );

		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'converts an AVS mismatch ruleset to matching UI config', () => {
		const ruleset = [
			{
				key: Rules.RULE_AVS_MISMATCH,
				outcome: Outcomes.BLOCK,
				check: {
					key: Checks.CHECK_AVS_CHECK,
					operator: CheckOperators.OPERATOR_EQUALS,
					value: false,
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			avs_mismatch: {
				block: true,
				enabled: true,
			},
		} );

		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'converts a CVC verification ruleset to matching UI config', () => {
		const ruleset = [
			{
				key: Rules.RULE_CVC_VERIFICATION,
				outcome: Outcomes.BLOCK,
				check: {
					key: Checks.CHECK_CVC_CHECK,
					operator: CheckOperators.OPERATOR_EQUALS,
					value: false,
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			cvc_verification: {
				block: true,
				enabled: true,
			},
		} );

		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'converts an international billing address ruleset to matching UI config', () => {
		const ruleset = [
			{
				key: Rules.RULE_INTERNATIONAL_BILLING_ADDRESS,
				outcome: Outcomes.BLOCK,
				check: {
					key: Checks.CHECK_BILLING_COUNTRY_SAME_WITH_ACCOUNT_COUNTRY,
					operator: CheckOperators.OPERATOR_EQUALS,
					value: false,
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			international_billing_address: {
				block: true,
				enabled: true,
			},
		} );

		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'converts an international ip address ruleset to matching UI config', () => {
		const ruleset = [
			{
				key: Rules.RULE_INTERNATIONAL_IP_ADDRESS,
				outcome: Outcomes.BLOCK,
				check: {
					key: Checks.CHECK_IP_COUNTRY_SAME_WITH_ACCOUNT_COUNTRY,
					operator: CheckOperators.OPERATOR_EQUALS,
					value: false,
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			international_ip_address: {
				block: true,
				enabled: true,
			},
		} );

		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'converts an order items threshold ruleset to matching UI config', () => {
		const ruleset = [
			{
				key: Rules.RULE_ORDER_ITEMS_THRESHOLD,
				outcome: Outcomes.BLOCK,
				check: {
					operator: CheckOperators.LIST_OPERATOR_OR,
					checks: [
						{
							key: Checks.CHECK_ITEM_COUNT,
							operator: CheckOperators.OPERATOR_LT,
							value: 1,
						},
						{
							key: Checks.CHECK_ITEM_COUNT,
							operator: CheckOperators.OPERATOR_GT,
							value: 10,
						},
					],
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			order_items_threshold: {
				block: true,
				enabled: true,
				max_items: 10,
				min_items: 1,
			},
		} );
		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'converts an order velocity ruleset to matching UI config', () => {
		const ruleset = [
			{
				key: Rules.RULE_ORDER_VELOCITY,
				outcome: Outcomes.BLOCK,
				check: {
					key: sprintf( 'orders_since_%dh', 12 ),
					operator: CheckOperators.OPERATOR_GT,
					value: 100,
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			order_velocity: {
				block: true,
				enabled: true,
				max_orders: 100,
				interval: 12,
			},
		} );
		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'converts a purchase price ruleset to matching UI config', () => {
		const ruleset = [
			{
				key: Rules.RULE_PURCHASE_PRICE_THRESHOLD,
				outcome: Outcomes.BLOCK,
				check: {
					operator: CheckOperators.LIST_OPERATOR_OR,
					checks: [
						{
							key: Checks.CHECK_ORDER_TOTAL,
							operator: CheckOperators.OPERATOR_LT,
							value: 1,
						},
						{
							key: Checks.CHECK_ORDER_TOTAL,
							operator: CheckOperators.OPERATOR_GT,
							value: 10,
						},
					],
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			purchase_price_threshold: {
				block: true,
				enabled: true,
				max_amount: 10,
				min_amount: 1,
			},
		} );
		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'converts an incomplete ruleset with the default values merged', () => {
		const ruleset = [
			{
				key: Rules.RULE_PURCHASE_PRICE_THRESHOLD,
				outcome: Outcomes.BLOCK,
				check: {
					operator: CheckOperators.LIST_OPERATOR_OR,
					checks: [
						{
							key: Checks.CHECK_ORDER_TOTAL,
							operator: CheckOperators.OPERATOR_LT,
							value: 1,
						},
					],
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			purchase_price_threshold: {
				block: true,
				enabled: true,
				max_amount: '',
				min_amount: 1,
			},
		} );
		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'test unknown key', () => {
		const ruleset = [
			{
				key: 'unknown_key',
				outcome: Outcomes.BLOCK,
				check: {
					key: Checks.CHECK_ORDER_TOTAL,
					operator: CheckOperators.OPERATOR_LT,
					value: 1,
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig );
		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'test unknown outcome', () => {
		const ruleset = [
			{
				key: Rules.RULE_PURCHASE_PRICE_THRESHOLD,
				outcome: 'nop',
				check: {
					key: Checks.CHECK_ORDER_TOTAL,
					operator: CheckOperators.OPERATOR_LT,
					value: 1,
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			[ Rules.RULE_PURCHASE_PRICE_THRESHOLD ]: {
				enabled: true,
				block: false,
				min_amount: 1,
				max_amount: '',
			},
		} );
		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'test unknown check key', () => {
		const ruleset = [
			{
				key: Rules.RULE_PURCHASE_PRICE_THRESHOLD,
				outcome: Outcomes.BLOCK,
				check: {
					key: 'unknown_key',
					operator: CheckOperators.OPERATOR_LT,
					value: 1,
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			[ Rules.RULE_PURCHASE_PRICE_THRESHOLD ]: {
				enabled: true,
				block: true,
				min_amount: '',
				max_amount: '',
			},
		} );
		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'test unknown check operator', () => {
		const ruleset = [
			{
				key: Rules.RULE_PURCHASE_PRICE_THRESHOLD,
				outcome: Outcomes.BLOCK,
				check: {
					key: Checks.CHECK_ORDER_TOTAL,
					operator: 'exp',
					value: 1,
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			[ Rules.RULE_PURCHASE_PRICE_THRESHOLD ]: {
				enabled: true,
				block: true,
				min_amount: '',
				max_amount: '',
			},
		} );
		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'test missing key', () => {
		const ruleset = [
			{
				outcome: Outcomes.BLOCK,
				check: {
					key: Checks.CHECK_ORDER_TOTAL,
					operator: CheckOperators.OPERATOR_LT,
					value: 1,
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig );
		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'test missing outcome', () => {
		const ruleset = [
			{
				key: Rules.RULE_PURCHASE_PRICE_THRESHOLD,
				check: {
					key: Checks.CHECK_ORDER_TOTAL,
					operator: CheckOperators.OPERATOR_LT,
					value: 1,
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			[ Rules.RULE_PURCHASE_PRICE_THRESHOLD ]: {
				enabled: true,
				block: false,
				min_amount: 1,
				max_amount: '',
			},
		} );
		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'test missing check key', () => {
		const ruleset = [
			{
				key: Rules.RULE_PURCHASE_PRICE_THRESHOLD,
				outcome: Outcomes.BLOCK,
				check: {
					operator: CheckOperators.OPERATOR_LT,
					value: 1,
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			[ Rules.RULE_PURCHASE_PRICE_THRESHOLD ]: {
				enabled: true,
				block: true,
				min_amount: '',
				max_amount: '',
			},
		} );
		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'test missing check operator', () => {
		const ruleset = [
			{
				key: Rules.RULE_PURCHASE_PRICE_THRESHOLD,
				outcome: Outcomes.BLOCK,
				check: {
					key: Checks.CHECK_ORDER_TOTAL,
					value: 1,
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			[ Rules.RULE_PURCHASE_PRICE_THRESHOLD ]: {
				enabled: true,
				block: true,
				min_amount: '',
				max_amount: '',
			},
		} );
		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'test missing check value', () => {
		const ruleset = [
			{
				key: Rules.RULE_PURCHASE_PRICE_THRESHOLD,
				outcome: Outcomes.BLOCK,
				check: {
					key: Checks.CHECK_ORDER_TOTAL,
					operator: CheckOperators.OPERATOR_LT,
				},
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			[ Rules.RULE_PURCHASE_PRICE_THRESHOLD ]: {
				enabled: true,
				block: true,
				min_amount: '',
				max_amount: '',
			},
		} );
		const output = readRuleset( ruleset );
		expect( output ).toEqual( expected );
	} );
	test( 'converts an empty UI config to an empty ruleset', () => {
		const config = {};
		const expected = [];
		const output = writeRuleset( config );
		expect( output ).toEqual( expected );
	} );

	test.each( [ true, false ] )(
		'converts enabled address mismatch filter to ruleset, blocking %s',
		( { block } ) => {
			const config = Object.assign( {}, defaultUIConfig, {
				[ Rules.RULE_ADDRESS_MISMATCH ]: {
					enabled: true,
					block: block,
				},
			} );
			const expected = [
				{
					key: Rules.RULE_ADDRESS_MISMATCH,
					outcome: block ? Outcomes.BLOCK : Outcomes.REVIEW,
					check: {
						key: Checks.CHECK_BILLING_SHIPPING_ADDRESS_SAME,
						operator: CheckOperators.OPERATOR_EQUALS,
						value: false,
					},
				},
			];
			const output = writeRuleset( config );
			expect( output ).toEqual( expected );
		}
	);
	test.each( [ true, false ] )(
		'converts enabled AVS mismatch filter to ruleset, blocking %s',
		( { block } ) => {
			const config = Object.assign( {}, defaultUIConfig, {
				[ Rules.RULE_AVS_MISMATCH ]: {
					enabled: true,
					block: block,
				},
			} );
			const expected = [
				{
					key: Rules.RULE_AVS_MISMATCH,
					outcome: block ? Outcomes.BLOCK : Outcomes.REVIEW,
					check: {
						key: Checks.CHECK_AVS_CHECK,
						operator: CheckOperators.OPERATOR_EQUALS,
						value: false,
					},
				},
			];
			const output = writeRuleset( config );
			expect( output ).toEqual( expected );
		}
	);
} );
