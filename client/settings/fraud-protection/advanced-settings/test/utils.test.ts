/* @format */
/**
 * Internal dependencies
 */
import {
	FraudProtectionRule,
	FraudProtectionSettingsMultipleChecks,
	FraudProtectionSettingsSingleCheck,
} from '../../interfaces';
import { CheckOperators, Checks, Outcomes, Rules } from '../constants';
import { readRuleset, writeRuleset } from '../utils';

const defaultUIConfig = {
	address_mismatch: {
		block: false,
		enabled: false,
	},
	ip_address_mismatch: {
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
	purchase_price_threshold: {
		block: false,
		enabled: false,
		max_amount: null,
		min_amount: null,
	},
};

declare const global: {
	wcpaySettings: {
		storeCurrency: string;
		connect: {
			country: string;
		};
		currencyData: {
			[ country: string ]: {
				code: string;
				symbol: string;
				symbolPosition: string;
				thousandSeparator: string;
				decimalSeparator: string;
				precision: number;
			};
		};
	};
	wcSettings: {
		admin: {
			preloadSettings: {
				general: {
					woocommerce_allowed_countries: string;
					woocommerce_specific_allowed_countries: string[];
					woocommerce_all_except_countries: string[];
				};
			};
		};
	};
};

describe( 'Ruleset adapter utilities test', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			storeCurrency: 'USD',
			connect: {
				country: 'US',
			},
			currencyData: {
				US: {
					code: 'USD',
					symbol: '$',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
			},
		};
	} );

	test( 'converts an empty ruleset to default UI config', () => {
		const ruleset: FraudProtectionRule[] = [];
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
				} as FraudProtectionSettingsSingleCheck,
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
	test( 'converts an international billing address ruleset to matching UI config', () => {
		const ruleset = [
			{
				key: Rules.RULE_IP_ADDRESS_MISMATCH,
				outcome: Outcomes.BLOCK,
				check: {
					key: Checks.CHECK_IP_BILLING_COUNTRY_SAME,
					operator: CheckOperators.OPERATOR_EQUALS,
					value: false,
				} as FraudProtectionSettingsSingleCheck,
			},
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			ip_address_mismatch: {
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
					key: Checks.CHECK_IP_COUNTRY,
					operator: CheckOperators.OPERATOR_IN,
					value: 'US|CA',
				} as FraudProtectionSettingsSingleCheck,
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
				} as FraudProtectionSettingsMultipleChecks,
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
							value: 100,
						},
						{
							key: Checks.CHECK_ORDER_TOTAL,
							operator: CheckOperators.OPERATOR_GT,
							value: 1000,
						},
					],
				} as FraudProtectionSettingsMultipleChecks,
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
							value: 100,
						},
					],
				} as FraudProtectionSettingsMultipleChecks,
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
				} as FraudProtectionSettingsSingleCheck,
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
					value: 100,
				} as FraudProtectionSettingsSingleCheck,
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
				} as FraudProtectionSettingsSingleCheck,
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
					value: 100,
				} as FraudProtectionSettingsSingleCheck,
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
					value: 100,
				},
			} as any,
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
			} as any,
		];
		const expected = Object.assign( {}, defaultUIConfig, {
			[ Rules.RULE_PURCHASE_PRICE_THRESHOLD ]: {
				enabled: true,
				block: false,
				min_amount: 0.01,
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
				} as FraudProtectionSettingsSingleCheck,
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
				} as FraudProtectionSettingsSingleCheck,
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
				} as FraudProtectionSettingsSingleCheck,
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
		const expected: FraudProtectionRule[] = [];
		const output = writeRuleset( config );
		expect( output ).toEqual( expected );
	} );
	test( 'converts default UI config to an empty ruleset', () => {
		const config = defaultUIConfig;
		const expected: FraudProtectionRule[] = [];
		const output = writeRuleset( config );
		expect( output ).toEqual( expected );
	} );
	test.each( [ true, false ] )(
		'converts enabled address mismatch filter to ruleset, blocking %s',
		( block ) => {
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
		'converts enabled ip address mismatch filter to ruleset, blocking %s',
		( block ) => {
			const config = Object.assign( {}, defaultUIConfig, {
				[ Rules.RULE_IP_ADDRESS_MISMATCH ]: {
					enabled: true,
					block: block,
				},
			} );
			const expected = [
				{
					key: Rules.RULE_IP_ADDRESS_MISMATCH,
					outcome: block ? Outcomes.BLOCK : Outcomes.REVIEW,
					check: {
						key: Checks.CHECK_IP_BILLING_COUNTRY_SAME,
						operator: CheckOperators.OPERATOR_EQUALS,
						value: false,
					},
				},
			];
			const output = writeRuleset( config );
			expect( output ).toEqual( expected );
		}
	);
	test.each( [
		[ true, 'all', 'in', '' ],
		[ true, 'all_except', 'in', 'TR|BR' ],
		[ true, 'specific', 'not_in', 'US|CA' ],
		[ false, 'all', 'in', '' ],
	] )(
		'converts enabled international ip address filter to ruleset, blocking %s',
		( block, allowType, checkOperator, checkValue ) => {
			global.wcSettings = {
				admin: {
					preloadSettings: {
						general: {
							woocommerce_allowed_countries: allowType,
							woocommerce_specific_allowed_countries: [
								'US',
								'CA',
							],
							woocommerce_all_except_countries: [ 'TR', 'BR' ],
						},
					},
				},
			};
			const config = Object.assign( {}, defaultUIConfig, {
				[ Rules.RULE_INTERNATIONAL_IP_ADDRESS ]: {
					enabled: true,
					block: block,
				},
			} );
			const expected = [
				{
					key: Rules.RULE_INTERNATIONAL_IP_ADDRESS,
					outcome: block ? Outcomes.BLOCK : Outcomes.REVIEW,
					check: {
						key: Checks.CHECK_IP_COUNTRY,
						operator: checkOperator,
						value: checkValue.toLowerCase(),
					},
				},
			];
			const output = writeRuleset( config );
			expect( output ).toEqual( expected );
		}
	);
	const orderItemsThresholdCases = [
		[ true, '', '' ],
		[ true, 10, '' ],
		[ true, '', 10 ],
		[ true, 10, 100 ],
		[ false, '', 100 ],
		[ false, 10, 100 ],
	] as Array< [ boolean, string | number, string | number ] >;
	test.each( orderItemsThresholdCases )(
		'converts enabled order items threshold filter to ruleset, blocking %s, min %s, max %s',
		( block, minItems, maxItems ) => {
			const config = Object.assign( {}, defaultUIConfig, {
				[ Rules.RULE_ORDER_ITEMS_THRESHOLD ]: {
					enabled: true,
					block: block,
					min_items: minItems,
					max_items: maxItems,
				},
			} );
			const expected = [];
			if ( '' !== minItems && '' !== maxItems ) {
				expected.push( {
					key: Rules.RULE_ORDER_ITEMS_THRESHOLD,
					outcome: block ? Outcomes.BLOCK : Outcomes.REVIEW,
					check: {
						operator: CheckOperators.LIST_OPERATOR_OR,
						checks: [
							{
								key: Checks.CHECK_ITEM_COUNT,
								operator: CheckOperators.OPERATOR_LT,
								value: minItems,
							},
							{
								key: Checks.CHECK_ITEM_COUNT,
								operator: CheckOperators.OPERATOR_GT,
								value: maxItems,
							},
						],
					},
				} );
			} else if ( '' !== minItems || '' !== maxItems ) {
				expected.push( {
					key: Rules.RULE_ORDER_ITEMS_THRESHOLD,
					outcome: block ? Outcomes.BLOCK : Outcomes.REVIEW,
					check: {
						key: Checks.CHECK_ITEM_COUNT,
						operator:
							'' !== maxItems
								? CheckOperators.OPERATOR_GT
								: CheckOperators.OPERATOR_LT,
						value: '' !== maxItems ? maxItems : minItems,
					},
				} );
			} else {
				expected.push( {
					key: Rules.RULE_ORDER_ITEMS_THRESHOLD,
					outcome: block ? Outcomes.BLOCK : Outcomes.REVIEW,
					check: null,
				} );
			}
			const output = writeRuleset( config );
			expect( output ).toEqual( expected );
		}
	);
	const purchasePriceThresholdCases = [
		[ true, '', '' ],
		[ true, 10, '' ],
		[ true, '', 10 ],
		[ true, 10, 100 ],
		[ false, '', 100 ],
		[ false, 10, 100 ],
	] as Array< [ boolean, string | number, string | number ] >;
	test.each( purchasePriceThresholdCases )(
		'converts enabled purchase price threshold filter to ruleset, blocking %s, min %s, max %s',
		( block, minAmount, maxAmount ) => {
			const config = Object.assign( {}, defaultUIConfig, {
				[ Rules.RULE_PURCHASE_PRICE_THRESHOLD ]: {
					enabled: true,
					block: block,
					min_amount: minAmount,
					max_amount: maxAmount,
				},
			} );
			const expected = [];
			if ( '' !== minAmount && '' !== maxAmount ) {
				expected.push( {
					key: Rules.RULE_PURCHASE_PRICE_THRESHOLD,
					outcome: block ? Outcomes.BLOCK : Outcomes.REVIEW,
					check: {
						operator: CheckOperators.LIST_OPERATOR_OR,
						checks: [
							{
								key: Checks.CHECK_ORDER_TOTAL,
								operator: CheckOperators.OPERATOR_LT,
								value: [
									Number( minAmount ) * 100,
									'USD',
								].join( '|' ),
							},
							{
								key: Checks.CHECK_ORDER_TOTAL,
								operator: CheckOperators.OPERATOR_GT,
								value: [
									Number( maxAmount ) * 100,
									'USD',
								].join( '|' ),
							},
						],
					},
				} );
			} else if ( '' !== minAmount || '' !== maxAmount ) {
				expected.push( {
					key: Rules.RULE_PURCHASE_PRICE_THRESHOLD,
					outcome: block ? Outcomes.BLOCK : Outcomes.REVIEW,
					check: {
						key: Checks.CHECK_ORDER_TOTAL,
						operator:
							'' !== maxAmount
								? CheckOperators.OPERATOR_GT
								: CheckOperators.OPERATOR_LT,
						value:
							'' !== maxAmount
								? [ Number( maxAmount ) * 100, 'USD' ].join(
										'|'
								  )
								: [ Number( minAmount ) * 100, 'USD' ].join(
										'|'
								  ),
					},
				} );
			} else {
				expected.push( {
					key: Rules.RULE_PURCHASE_PRICE_THRESHOLD,
					outcome: block ? Outcomes.BLOCK : Outcomes.REVIEW,
					check: null,
				} );
			}
			const output = writeRuleset( config );
			expect( output ).toEqual( expected );
		}
	);
} );
