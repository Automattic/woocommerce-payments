/**
 * External dependencies
 */
import { render, screen, waitFor } from '@testing-library/react';
import $ from 'jquery';
import { recordUserEvent } from 'tracks';
import apiFetch from '@wordpress/api-fetch';
import {
	cartWithItemsMock,
	cartWithItemsAndCouponMock,
} from '../__fixtures__/cart';

jest.mock( 'tracks', () => ( {
	recordUserEvent: jest.fn(),
} ) );
jest.mock( 'lodash', () => ( {
	debounce: jest.fn( ( callback ) => callback ),
} ) );
jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

describe( 'Tokenized Express Checkout Element - Shortcode checkout page logic', () => {
	let stripeElementMock, stripeInstance;
	beforeEach( () => {
		apiFetch.mockClear();
		apiFetch.mockImplementation( async () =>
			Promise.resolve( {
				json: () => Promise.resolve( cartWithItemsMock ),
				headers: new Map(),
			} )
		);
		// ensuring jQuery is available globally.
		global.$ = global.jQuery = $;
		// ensuring that `callback` is immediately invoked on document.ready.
		$.fn.ready = ( callback ) => callback( $ );
		global.jQuery.blockUI = () => null;
		global.jQuery.unblockUI = () => null;

		global.wcpayExpressCheckoutParams = {};
		global.wcpayExpressCheckoutParams.nonce = {
			store_api_nonce: 'store_api_nonce',
		};
		global.wcpayExpressCheckoutParams.stripe = {
			accountId: 'acc_id',
			locale: 'it',
			publishableKey: 'stripe_public_key',
		};
		global.wcpayExpressCheckoutParams.checkout = {
			country_code: 'US',
			currency_code: 'usd',
			currency_decimals: 2,
			needs_payer_phone: false,
			needs_shipping: true,
			allowed_shipping_countries: [ 'US' ],
		};
		global.wcpayExpressCheckoutParams.store_name = 'My fancy store';
		global.wcpayExpressCheckoutParams.button_context = 'checkout';

		// just mocking some server-side-provided DOM elements.
		render(
			<div>
				<div className="woocommerce-notices-wrapper" />
				<div id="wcpay-express-checkout-wrapper">
					<div
						id="wcpay-express-checkout-element"
						data-testid="wcpay-express-checkout-element"
					/>
				</div>
			</div>
		);

		const stripeElementRegisteredEventCallbacks = {};
		stripeElementMock = {
			submit: jest.fn(),
			mount: jest.fn(),
			unmount: jest.fn(),
			__getRegisteredEvent: ( eventName ) =>
				stripeElementRegisteredEventCallbacks[ eventName ],
			on: jest.fn( ( event, callback ) => {
				stripeElementRegisteredEventCallbacks[ event ] = callback;
			} ),
		};
		global.Stripe = jest.fn( () => {
			stripeInstance = {
				elements: jest.fn( () => ( {
					create: jest.fn( () => stripeElementMock ),
				} ) ),
			};

			return stripeInstance;
		} );
	} );

	afterEach( async () => {
		delete global.Stripe;
		// removing all the registered event handlers so they don't leak between tests.
		global.$( document.body ).off();
	} );

	it( 'should not initialize Stripe if there is no publishable key', async () => {
		global.wcpayExpressCheckoutParams.stripe.publishableKey = '';

		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		expect( global.Stripe ).not.toHaveBeenCalled();
		expect( recordUserEvent ).not.toHaveBeenCalled();
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).not.toHaveClass( 'is-ready' );
	} );

	it( 'should track the initialization', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		expect( global.Stripe ).not.toHaveBeenCalled();

		$( document.body ).trigger( 'updated_checkout' );

		await waitFor( () =>
			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					method: 'GET',
					path: expect.stringContaining( '/wc/store/v1/cart' ),
				} )
			)
		);
		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );
		expect( stripeInstance.elements ).toHaveBeenCalledWith( {
			mode: 'payment',
			amount: 3697,
			currency: 'usd',
			paymentMethodCreation: 'manual',
			appearance: expect.anything(),
			locale: 'it',
		} );

		// triggering the `ready` event on the ECE button, to test its callback.
		stripeElementMock.__getRegisteredEvent( 'ready' )( {
			availablePaymentMethods: {
				link: false,
				applePay: true,
				googlePay: true,
				paypal: false,
				amazonPay: false,
			},
		} );

		expect( recordUserEvent ).toHaveBeenNthCalledWith(
			1,
			'applepay_button_load',
			expect.objectContaining( { source: 'checkout' } )
		);
		expect( recordUserEvent ).toHaveBeenNthCalledWith(
			2,
			'gpay_button_load',
			expect.objectContaining( { source: 'checkout' } )
		);
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).toBeVisible();
	} );

	it( 'should ensure that new cart totals are fetched on the `updated_checkout` event', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		$( document.body ).trigger( 'updated_checkout' );

		await waitFor( () => expect( global.Stripe ).toHaveBeenCalled() );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).toBeVisible();

		// triggering the `click` event on the ECE button, to test its callback.
		const clickEventResolveMock = jest.fn();
		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: clickEventResolveMock,
			expressPaymentType: 'google_pay',
		} );

		expect( clickEventResolveMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				lineItems: [
					{ amount: 2399, name: 'Beanie' },
					{ amount: 1100, name: 'Shipping' },
					{ amount: 198, name: 'Tax' },
				],
				shippingAddressRequired: true,
				shippingRates: [
					{
						amount: 1100,
						deliveryEstimate: '',
						displayName: 'Flat rate',
						id: 'flat_rate:1',
					},
					{
						amount: 2200,
						deliveryEstimate: '',
						displayName: 'Express shipping',
						id: 'flat_rate:5',
					},
				],
			} )
		);

		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: clickEventResolveMock,
			expressPaymentType: 'google_pay',
		} );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );

		apiFetch.mockImplementation( async () =>
			Promise.resolve( {
				json: () => Promise.resolve( cartWithItemsAndCouponMock ),
				headers: new Map(),
			} )
		);

		// with the new API response, trigger `updated_checkout` again.
		$( document.body ).trigger( 'updated_checkout' );

		await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 2 ) );

		// since this time the totals should be `0`, there should be no button.
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).not.toBeVisible();
	} );
} );
