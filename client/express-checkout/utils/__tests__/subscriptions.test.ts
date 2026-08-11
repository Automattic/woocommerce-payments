/**
 * External dependencies
 */
import { addFilter, removeFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import {
	cartHasAnySubscription,
	getSetupFutureUsageForCart,
	getSetupFutureUsageForContext,
} from '../subscriptions';

const testFilterNamespace = 'wcpay-test/setup-future-usage';

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
	afterEach( () => {
		removeFilter(
			'wcpay.express-checkout.setup-future-usage',
			testFilterNamespace
		);
	} );

	describe( 'falling back to the WC Subscriptions heuristic', () => {
		// A cart response that carries no `wcpay` extension degrades to the old
		// WC Subscriptions detection rather than to "never save".
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

		it( 'falls back when the wcpay extension omits the key', () => {
			expect(
				getSetupFutureUsageForCart( {
					items: [],
					extensions: {
						subscriptions: [ buildSubscriptionSchedule() ],
						wcpay: {},
					},
				} )
			).toBe( 'off_session' );
		} );
	} );

	describe( 'preferring the server-computed value', () => {
		// The WOOPMNT-6335 case: a cart with no subscription the client can see, whose
		// payment method the server will nonetheless save.
		it( 'returns off_session the server declares for a cart with no subscription', () => {
			expect(
				getSetupFutureUsageForCart( {
					...regularCart,
					extensions: {
						wcpay: { setup_future_usage: 'off_session' },
					},
				} )
			).toBe( 'off_session' );
		} );

		// Presence beats truthiness: an explicit null is the server deciding, not an absence.
		it( 'returns null the server declares even when the heuristic would say off_session', () => {
			expect(
				getSetupFutureUsageForCart( {
					items: [],
					extensions: {
						subscriptions: [ buildSubscriptionSchedule() ],
						wcpay: { setup_future_usage: null },
					},
				} )
			).toBeNull();
		} );
	} );

	describe( 'the extensibility filter', () => {
		it( 'can declare off_session for a cart that looks regular', () => {
			addFilter(
				'wcpay.express-checkout.setup-future-usage',
				testFilterNamespace,
				() => 'off_session'
			);

			expect( getSetupFutureUsageForCart( regularCart ) ).toBe(
				'off_session'
			);
		} );

		it( 'can suppress off_session and receives the cart data', () => {
			const seen: unknown[] = [];
			addFilter(
				'wcpay.express-checkout.setup-future-usage',
				testFilterNamespace,
				( value: unknown, cartData: unknown ) => {
					seen.push( cartData );
					return null;
				}
			);

			expect(
				getSetupFutureUsageForCart( {
					items: [],
					extensions: {
						wcpay: { setup_future_usage: 'off_session' },
					},
				} )
			).toBeNull();
			expect( seen ).toHaveLength( 1 );
		} );
	} );
} );

describe( 'getSetupFutureUsageForContext', () => {
	afterEach( () => {
		removeFilter(
			'wcpay.express-checkout.setup-future-usage',
			testFilterNamespace
		);
		delete ( global as Record< string, unknown > )
			.wcpayExpressCheckoutParams;
	} );

	it( 'returns the localized value', () => {
		( global as Record< string, unknown > ).wcpayExpressCheckoutParams = {
			setup_future_usage: 'off_session',
		};

		expect( getSetupFutureUsageForContext() ).toBe( 'off_session' );
	} );

	it( 'returns null when the localized value is absent', () => {
		( global as Record< string, unknown > ).wcpayExpressCheckoutParams = {};

		expect( getSetupFutureUsageForContext() ).toBeNull();
	} );

	it( 'runs through the same filter as the cart path', () => {
		( global as Record< string, unknown > ).wcpayExpressCheckoutParams = {};
		addFilter(
			'wcpay.express-checkout.setup-future-usage',
			testFilterNamespace,
			() => 'off_session'
		);

		expect( getSetupFutureUsageForContext() ).toBe( 'off_session' );
	} );

	// `wcpay_express_checkout_js_params` is a documented extension point, and
	// `has_subscription` was the only lever before `setup_future_usage` existed — quite
	// possibly forced by an integration working around this very bug.
	describe( 'legacy has_subscription overrides', () => {
		it( 'honours has_subscription when the server declared nothing', () => {
			( global as Record< string, unknown > ).wcpayExpressCheckoutParams =
				{
					setup_future_usage: null,
					has_subscription: true,
				};

			expect( getSetupFutureUsageForContext() ).toBe( 'off_session' );
		} );

		it( 'prefers the server value over the legacy flag', () => {
			( global as Record< string, unknown > ).wcpayExpressCheckoutParams =
				{
					setup_future_usage: 'off_session',
					has_subscription: false,
				};

			expect( getSetupFutureUsageForContext() ).toBe( 'off_session' );
		} );

		it( 'returns null when neither is set', () => {
			( global as Record< string, unknown > ).wcpayExpressCheckoutParams =
				{
					setup_future_usage: null,
					has_subscription: false,
				};

			expect( getSetupFutureUsageForContext() ).toBeNull();
		} );
	} );
} );
