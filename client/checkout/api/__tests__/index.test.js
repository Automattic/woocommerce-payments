/**
 * Internal dependencies
 */
import WCPayAPI from '..';
import request from 'wcpay/checkout/utils/request';

jest.mock( 'wcpay/checkout/utils/request', () =>
	jest.fn( () => Promise.resolve( {} ).finally( () => {} ) )
);
jest.mock( 'wcpay/utils/express-checkout', () => ( {
	buildAjaxURL: jest.fn(),
	getExpressCheckoutConfig: jest.fn(),
} ) );
jest.mock( 'wcpay/utils/checkout', () => ( {
	getConfig: jest.fn(),
} ) );

describe( 'WCPayAPI', () => {
	describe( 'getStripe', () => {
		afterEach( () => {
			jest.useRealTimers();
			window.Stripe = undefined;
		} );

		test( 'waits for Stripe to be available in the global scope', async () => {
			jest.useFakeTimers();
			const api = new WCPayAPI( {}, request );
			let stripeInstance = null;

			api.getStripe().then( ( result ) => {
				stripeInstance = result;
			} );

			jest.runOnlyPendingTimers();
			await Promise.resolve();

			expect( stripeInstance ).toBeNull();

			window.Stripe = function Stripe() {};

			jest.runOnlyPendingTimers();
			await Promise.resolve();

			jest.runOnlyPendingTimers();
			await Promise.resolve();

			expect( stripeInstance ).toBeInstanceOf( window.Stripe );
		} );

		test( 'resolves immediately if Stripe is already available', async () => {
			const api = new WCPayAPI( {}, request );
			window.Stripe = function Stripe() {};
			const stripeInstance = await api.getStripe();
			expect( stripeInstance ).toBeInstanceOf( window.Stripe );
		} );
	} );
} );
