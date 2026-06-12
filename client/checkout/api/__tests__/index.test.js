/**
 * Internal dependencies
 */
import WCPayAPI from '..';
import request from 'wcpay/checkout/utils/request';
import { getConfig } from 'wcpay/utils/checkout';

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
	beforeEach( () => {
		getConfig.mockReset();
		// Normal, non-compromised page: the legitimate Stripe.js handle tag is
		// present, so the origin assertion passes by default.
		addStripeScript( 'https://js.stripe.com/v3/?ver=3.0' );
	} );

	afterEach( () => {
		jest.useRealTimers();
		jest.restoreAllMocks();
		window.Stripe = undefined;
		clearScripts();
	} );

	describe( 'getStripe', () => {
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

		const useAttackerOrigin = () => {
			clearScripts();
			addStripeScript( 'https://js.evil.example/v3/?ver=3.0' );
		};

		beforeEach( () => {
			window.Stripe = function Stripe() {};
			warn = jest.spyOn( console, 'warn' ).mockImplementation( () => {} );
		} );

		test( 'blocks by default (unknown mode) when the origin is wrong', async () => {
			getConfig.mockReturnValue( undefined );
			useAttackerOrigin();
			const api = new WCPayAPI( {}, request );

			await expect( api.getStripe() ).rejects.toThrow(
				'unexpected origin'
			);
		} );

		test( "throws in 'block' mode when the origin is wrong", async () => {
			getConfig.mockReturnValue( 'block' );
			useAttackerOrigin();
			const api = new WCPayAPI( {}, request );

			await expect( api.getStripe() ).rejects.toThrow(
				'https://js.evil.example/v3/?ver=3.0'
			);
		} );

		test( "warns but resolves in 'report' mode when the origin is wrong", async () => {
			getConfig.mockReturnValue( 'report' );
			useAttackerOrigin();
			const api = new WCPayAPI( {}, request );

			const stripeInstance = await api.getStripe();

			expect( stripeInstance ).toBeInstanceOf( window.Stripe );
			expect( warn ).toHaveBeenCalledWith(
				expect.stringContaining( 'js.evil.example' )
			);
		} );

		test( "skips the check in 'off' mode", async () => {
			getConfig.mockReturnValue( 'off' );
			useAttackerOrigin();
			const api = new WCPayAPI( {}, request );

			const stripeInstance = await api.getStripe();

			expect( stripeInstance ).toBeInstanceOf( window.Stripe );
			expect( warn ).not.toHaveBeenCalled();
		} );

		test( 'resolves without warning when the origin is legitimate', async () => {
			getConfig.mockReturnValue( 'block' );
			const api = new WCPayAPI( {}, request );

			const stripeInstance = await api.getStripe();

			expect( stripeInstance ).toBeInstanceOf( window.Stripe );
			expect( warn ).not.toHaveBeenCalled();
		} );
	} );
} );
