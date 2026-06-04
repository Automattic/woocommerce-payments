/**
 * Internal dependencies
 */
import {
	cartHasAnySubscription,
	getSetupFutureUsageForCart,
} from '../subscriptions';

const buildSubscriptionSchedule = ( { billingPeriod = 'month' } = {} ) => ( {
	billing_period: billingPeriod,
	billing_interval: 1,
	trial_length: 1,
	totals: { total_price: '1999' },
} );

const buildTrialSubscriptionItem = () => ( {
	name: 'Subscription Product',
	quantity: 1,
	variation: [],
	item_data: [],
	extensions: {
		subscriptions: {
			billing_period: 'month',
			billing_interval: 1,
			trial_length: 1,
		},
	},
	totals: {
		line_subtotal: '0',
		line_subtotal_tax: '0',
		currency_minor_unit: 2,
	},
	prices: {
		price: '0',
		currency_minor_unit: 2,
	},
} );

const regularCart = {
	items: [
		{
			name: 'Regular Product',
			quantity: 1,
			variation: [],
			item_data: [],
			totals: {
				line_subtotal: '2399',
				line_subtotal_tax: '198',
				currency_minor_unit: 2,
			},
			prices: {
				price: '2399',
				currency_minor_unit: 2,
			},
		},
	],
	extensions: {},
};

describe( 'cartHasAnySubscription', () => {
	it( 'returns false when cartData is undefined', () => {
		expect( cartHasAnySubscription( undefined ) ).toBe( false );
	} );

	it( 'returns false when extensions is missing', () => {
		expect( cartHasAnySubscription( { items: [] } ) ).toBe( false );
	} );

	it( 'returns false when extensions.subscriptions is missing', () => {
		expect( cartHasAnySubscription( { items: [], extensions: {} } ) ).toBe(
			false
		);
	} );

	it( 'returns false when extensions.subscriptions is an empty array', () => {
		expect(
			cartHasAnySubscription( {
				items: [],
				extensions: { subscriptions: [] },
			} )
		).toBe( false );
	} );

	it( 'returns true when cart contains a single trial subscription schedule', () => {
		expect(
			cartHasAnySubscription( {
				items: [],
				extensions: {
					subscriptions: [ buildSubscriptionSchedule() ],
				},
			} )
		).toBe( true );
	} );

	it( 'returns true when cart contains a non-trial recurring subscription', () => {
		expect(
			cartHasAnySubscription( {
				items: [],
				extensions: {
					subscriptions: [
						{
							billing_period: 'month',
							billing_interval: 1,
							trial_length: 0,
							totals: { total_price: '1999' },
						},
					],
				},
			} )
		).toBe( true );
	} );

	it( 'returns true when cart contains multiple subscription schedules', () => {
		expect(
			cartHasAnySubscription( {
				items: [],
				extensions: {
					subscriptions: [
						buildSubscriptionSchedule(),
						buildSubscriptionSchedule( { billingPeriod: 'year' } ),
					],
				},
			} )
		).toBe( true );
	} );

	it( 'returns true when only an item carries the subscriptions extension', () => {
		expect(
			cartHasAnySubscription( {
				items: [ buildTrialSubscriptionItem() ],
				extensions: {},
			} )
		).toBe( true );
	} );

	it( 'returns false when a regular item carries an empty subscriptions extension', () => {
		expect(
			cartHasAnySubscription( {
				items: [
					{
						name: 'Regular Product',
						extensions: {
							subscriptions: {},
						},
					},
				],
				extensions: {},
			} )
		).toBe( false );
	} );

	it( 'returns false when a regular item carries an all-null subscriptions extension', () => {
		expect(
			cartHasAnySubscription( {
				items: [
					{
						name: 'Regular Product',
						extensions: {
							subscriptions: {
								billing_period: null,
								billing_interval: null,
								trial_length: null,
							},
						},
					},
				],
				extensions: {},
			} )
		).toBe( false );
	} );

	it( 'returns false for a regular cart whose items have no subscriptions extension', () => {
		expect( cartHasAnySubscription( regularCart ) ).toBe( false );
	} );
} );

describe( 'getSetupFutureUsageForCart', () => {
	it( 'returns null for a regular cart', () => {
		expect( getSetupFutureUsageForCart( regularCart ) ).toBeNull();
	} );

	it( 'returns off_session when the cart contains a subscription', () => {
		expect(
			getSetupFutureUsageForCart( {
				items: [],
				extensions: {
					subscriptions: [ buildSubscriptionSchedule() ],
				},
			} )
		).toBe( 'off_session' );
	} );
} );
