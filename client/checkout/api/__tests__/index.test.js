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

const addStripeScript = ( src, id = 'stripe-js' ) => {
	const script = document.createElement( 'script' );
	if ( id ) {
		script.id = id;
	}
	script.src = src;
	document.head.appendChild( script );
};

const clearScripts = () =>
	document.head
		.querySelectorAll( 'script' )
		.forEach( ( script ) => script.remove() );

describe( 'WCPayAPI', () => {
	describe( 'getStripe', () => {
		beforeEach( () => {
			// Normal, non-compromised page: the legitimate Stripe.js handle tag
			// is present, so the origin assertion passes silently.
			addStripeScript( 'https://js.stripe.com/v3/?ver=3.0' );
		} );

		afterEach( () => {
			jest.useRealTimers();
			jest.restoreAllMocks();
			window.Stripe = undefined;
			clearScripts();
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

	describe( 'Stripe.js origin assertion', () => {
		let warn;

		beforeEach( () => {
			window.Stripe = function Stripe() {};
			warn = jest.spyOn( console, 'warn' ).mockImplementation( () => {} );
		} );

		afterEach( () => {
			jest.restoreAllMocks();
			window.Stripe = undefined;
			clearScripts();
		} );

		test( 'blocks the payment and warns when the origin is wrong', async () => {
			addStripeScript( 'https://js.evil.example/v3/?ver=3.0' );
			const api = new WCPayAPI( {}, request );

			await expect( api.getStripe() ).rejects.toThrow(
				/provenance check failed/
			);
			expect( warn ).toHaveBeenCalledWith(
				expect.stringContaining( 'js.evil.example' )
			);
		} );

		test( 'fails fast on a wrong origin even if window.Stripe never loads', async () => {
			window.Stripe = undefined;
			addStripeScript( 'https://js.evil.example/v3/?ver=3.0' );
			const api = new WCPayAPI( {}, request );

			await expect( api.getStripe() ).rejects.toThrow(
				/provenance check failed/
			);
		} );

		test( 'blocks with a clear message when no Stripe.js tag is present', async () => {
			const api = new WCPayAPI( {}, request );

			await expect( api.getStripe() ).rejects.toThrow(
				/provenance check failed/
			);
			expect( warn ).toHaveBeenCalledWith(
				expect.stringContaining( 'no Stripe.js script tag' )
			);
		} );

		test( 'resolves without warning when the origin is legitimate', async () => {
			addStripeScript( 'https://js.stripe.com/v3/?ver=3.0' );
			const api = new WCPayAPI( {}, request );

			const stripeInstance = await api.getStripe();

			expect( stripeInstance ).toBeInstanceOf( window.Stripe );
			expect( warn ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'createStripe origin assertion', () => {
		let warn;

		beforeEach( () => {
			window.Stripe = function Stripe() {};
			warn = jest.spyOn( console, 'warn' ).mockImplementation( () => {} );
		} );

		afterEach( () => {
			jest.restoreAllMocks();
			window.Stripe = undefined;
			clearScripts();
		} );

		// createStripe() is the single point where `new Stripe()` is built, and
		// confirmIntent's WooPay branch calls it directly (bypassing getStripe),
		// so the origin must be asserted here too.
		test( 'blocks a direct createStripe() call on a wrong origin', () => {
			addStripeScript( 'https://js.evil.example/v3/?ver=3.0' );
			const api = new WCPayAPI( {}, request );

			expect( () => api.createStripe( 'pk_test_123', 'en' ) ).toThrow(
				/provenance check failed/
			);
			expect( warn ).toHaveBeenCalledWith(
				expect.stringContaining( 'js.evil.example' )
			);
		} );

		test( 'builds the Stripe instance when the origin is legitimate', () => {
			addStripeScript( 'https://js.stripe.com/v3/?ver=3.0' );
			const api = new WCPayAPI( {}, request );

			const stripeInstance = api.createStripe( 'pk_test_123', 'en' );

			expect( stripeInstance ).toBeInstanceOf( window.Stripe );
			expect( warn ).not.toHaveBeenCalled();
		} );
	} );
} );
