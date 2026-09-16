/**
 * Internal dependencies
 */
import {
	getSetupFutureUsageForCart,
	getLocalizedSetupFutureUsage,
} from '../subscriptions';

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

describe( 'getSetupFutureUsageForCart', () => {
	it( 'returns the value the server declared on the cart', () => {
		expect(
			getSetupFutureUsageForCart( {
				...regularCart,
				extensions: {
					wcpay: { setup_future_usage: 'off_session' },
				},
			} )
		).toBe( 'off_session' );
	} );

	it( 'returns null the server declared even when the cart looks like a subscription', () => {
		expect(
			getSetupFutureUsageForCart( {
				items: [],
				extensions: {
					subscriptions: [
						{
							billing_period: 'month',
							billing_interval: 1,
						},
					],
					wcpay: { setup_future_usage: null },
				},
			} )
		).toBeNull();
	} );

	it( 'returns null when the wcpay extension is missing', () => {
		expect( getSetupFutureUsageForCart( regularCart ) ).toBeNull();
	} );

	it( 'returns null when the wcpay extension omits the key', () => {
		expect(
			getSetupFutureUsageForCart( {
				items: [],
				extensions: { wcpay: {} },
			} )
		).toBeNull();
	} );
} );

describe( 'getLocalizedSetupFutureUsage', () => {
	afterEach( () => {
		delete ( global as Record< string, unknown > )
			.wcpayExpressCheckoutParams;
	} );

	it( 'returns the localized value', () => {
		( global as Record< string, unknown > ).wcpayExpressCheckoutParams = {
			setup_future_usage: 'off_session',
		};

		expect( getLocalizedSetupFutureUsage() ).toBe( 'off_session' );
	} );

	it( 'returns null when the localized value is absent', () => {
		( global as Record< string, unknown > ).wcpayExpressCheckoutParams = {};

		expect( getLocalizedSetupFutureUsage() ).toBeNull();
	} );
} );

describe( 'getSetupFutureUsageForCart on pay-for-order', () => {
	afterEach( () => {
		delete ( global as Record< string, unknown > )
			.wcpayExpressCheckoutParams;
	} );

	it( 'uses the localized value because the order API has no cart extension', () => {
		( global as Record< string, unknown > ).wcpayExpressCheckoutParams = {
			button_context: 'pay_for_order',
			setup_future_usage: 'off_session',
		};

		expect( getSetupFutureUsageForCart( regularCart ) ).toBe(
			'off_session'
		);
	} );

	it( 'still returns null when the order carries no subscription', () => {
		( global as Record< string, unknown > ).wcpayExpressCheckoutParams = {
			button_context: 'pay_for_order',
			setup_future_usage: null,
		};

		expect( getSetupFutureUsageForCart( regularCart ) ).toBeNull();
	} );

	it( 'does not use the localized value in other contexts', () => {
		( global as Record< string, unknown > ).wcpayExpressCheckoutParams = {
			button_context: 'checkout',
			setup_future_usage: 'off_session',
		};

		expect( getSetupFutureUsageForCart( regularCart ) ).toBeNull();
	} );
} );
