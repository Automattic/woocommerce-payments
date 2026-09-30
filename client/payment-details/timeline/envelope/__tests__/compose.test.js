/** @format **/

/**
 * Internal dependencies
 */
import { composeCapturedBodyFromBreakdown } from '../compose';

const findBreakdownList = ( lines ) =>
	lines.find(
		( line ) =>
			line &&
			typeof line === 'object' &&
			line.props?.className === 'fee-breakdown-list'
	);

describe( 'composeCapturedBodyFromBreakdown — discount row split', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			shouldUseExplicitPrice: true,
			zeroDecimalCurrencies: [ 'jpy' ],
			connect: { country: 'US' },
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

	const buildEnvelopeWithDiscount = () => ( {
		fee_breakdown_v1: {
			rows: [
				{
					key: 'base',
					kind: 'fee',
					label: null,
					amount: 59,
					currency: 'usd',
					rate: {
						percentage: 0.029,
						fixed: 30,
						fixed_currency: 'usd',
						percentage_display: '2.9%',
					},
					meta: null,
					display_amount: -59,
				},
				{
					key: 'additional.international',
					kind: 'fee',
					label: null,
					amount: 0,
					currency: 'usd',
					rate: {
						percentage: 0.015,
						fixed: 0,
						fixed_currency: 'usd',
						percentage_display: '1.5%',
					},
					meta: null,
					display_amount: 0,
				},
				{
					key: 'discount.wcpay-promo-2023',
					kind: 'adjustment',
					label: null,
					// Promo discount — preserved negative deltas off the
					// fee history. Both `percentage` and `fixed` carry
					// negative signs end-to-end so the timeline shows
					// "Variable fee: -0.15%" / "Fixed fee: -$0.02".
					amount: 0,
					currency: 'usd',
					rate: {
						percentage: -0.0015,
						fixed: -2,
						fixed_currency: 'usd',
						percentage_display: '-0.15%',
					},
					meta: { fee_id: 'wcpay-promo-2023' },
					display_amount: 0,
				},
			],
			totals: {
				fee: {
					key: null,
					amount: 57,
					display_amount: -57,
					currency: 'usd',
					rate: {
						percentage: 0.0425,
						fixed: 28,
						fixed_currency: 'usd',
						percentage_display: '4.25%',
					},
				},
				tax: { amount: 0, display_amount: 0, currency: 'usd' },
				net: { amount: 943, currency: 'usd' },
				capture_net: { amount: 943, currency: 'usd' },
				gross: { amount: 1000, currency: 'usd' },
			},
			notes: [],
		},
	} );

	test( 'discount row renders as parent label + signed Variable/Fixed sub-bullets', () => {
		const event = buildEnvelopeWithDiscount();
		const lines = composeCapturedBodyFromBreakdown( event );
		const list = findBreakdownList( lines );
		expect( list ).toBeTruthy();

		const items = Array.isArray( list.props.children )
			? list.props.children
			: [ list.props.children ];

		// Find the discount item — it's the only `<li>` whose children are
		// an array (label + nested <ul>), the others render flat strings.
		const discountItem = items.find(
			( li ) =>
				li &&
				Array.isArray( li.props?.children ) &&
				li.props.children.some(
					( child ) =>
						child &&
						typeof child === 'object' &&
						child.props?.className === 'discount-split-list'
				)
		);
		expect( discountItem ).toBeTruthy();

		const subUl = discountItem.props.children.find(
			( child ) =>
				child &&
				typeof child === 'object' &&
				child.props?.className === 'discount-split-list'
		);
		const subItems = Array.isArray( subUl.props.children )
			? subUl.props.children
			: [ subUl.props.children ];
		const subTexts = subItems.map( ( li ) => li.props.children );

		// Negative signs preserved on both the percentage and the fixed
		// component — matches the legacy `map-events.js` snapshot output.
		expect( subTexts ).toEqual( [
			'Variable fee: -0.15%',
			'Fixed fee: -$0.02',
		] );
	} );
} );
