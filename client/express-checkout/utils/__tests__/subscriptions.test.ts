/**
 * Internal dependencies
 */
import {
	resolveSetupFutureUsage,
	getLocalizedSetupFutureUsage,
} from '../subscriptions';

describe( 'resolveSetupFutureUsage', () => {
	it( 'returns the value the server declared on the cart', () => {
		expect(
			resolveSetupFutureUsage( {
				extensions: {
					wcpay: { setup_future_usage: 'off_session' },
				},
			} )
		).toBe( 'off_session' );
	} );

	it( 'returns null the server declared even when the cart looks like a subscription', () => {
		expect(
			resolveSetupFutureUsage( {
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
		expect( resolveSetupFutureUsage( { extensions: {} } ) ).toBeNull();
	} );

	it( 'returns null when the wcpay extension omits the key', () => {
		expect(
			resolveSetupFutureUsage( {
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

describe( 'resolveSetupFutureUsage on pay-for-order', () => {
	afterEach( () => {
		delete ( global as Record< string, unknown > )
			.wcpayExpressCheckoutParams;
	} );

	it( 'uses the localized value because the order API has no cart extension', () => {
		( global as Record< string, unknown > ).wcpayExpressCheckoutParams = {
			button_context: 'pay_for_order',
			setup_future_usage: 'off_session',
		};

		expect( resolveSetupFutureUsage( { extensions: {} } ) ).toBe(
			'off_session'
		);
	} );

	it( 'still returns null when the order carries no subscription', () => {
		( global as Record< string, unknown > ).wcpayExpressCheckoutParams = {
			button_context: 'pay_for_order',
			setup_future_usage: null,
		};

		expect( resolveSetupFutureUsage( { extensions: {} } ) ).toBeNull();
	} );

	it( 'does not use the localized value in other contexts', () => {
		( global as Record< string, unknown > ).wcpayExpressCheckoutParams = {
			button_context: 'checkout',
			setup_future_usage: 'off_session',
		};

		expect( resolveSetupFutureUsage( { extensions: {} } ) ).toBeNull();
	} );
} );
