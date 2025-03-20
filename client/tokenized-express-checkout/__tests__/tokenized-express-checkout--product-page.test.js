/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import $ from 'jquery';
import { recordUserEvent } from 'tracks';

jest.mock( 'tracks', () => ( {
	recordUserEvent: jest.fn(),
} ) );
jest.mock( 'lodash', () => ( {
	debounce: jest.fn( ( callback ) => callback ),
} ) );

describe( 'Tokenized Express Checkout Element - Product page logic', () => {
	let stripeElementMock, stripeInstance;
	beforeEach( () => {
		// ensuring jQuery is available globally.
		global.$ = global.jQuery = $;
		// ensuring that `callback` is immediately invoked on document.ready.
		$.fn.ready = ( callback ) => callback( $ );

		global.wcpayExpressCheckoutParams = {};
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
		global.wcpayExpressCheckoutParams.button_context = 'product';
		global.wcpayExpressCheckoutParams.product = {
			product_type: 'simple',
			needs_shipping: true,
			country_code: 'US',
			currency: 'usd',
			total: { amount: 1100 },
			shippingOptions: {
				id: 'pending',
				label: 'Pending',
				detail: '',
				amount: 0,
			},
			displayItems: [
				{
					label: 'Beanie',
					amount: 1000,
				},
				{
					label: 'Tax',
					amount: 100,
					pending: false,
				},
				{
					label: 'Shipping',
					amount: 0,
					pending: true,
				},
			],
		};

		// just mocking some server-side-provided DOM elements.
		render(
			<div>
				<button className="single_add_to_cart_button">
					Fake button
				</button>
				<div id="wcpay-express-checkout-wrapper">
					<div
						id="wcpay-express-checkout-element"
						data-testid="wcpay-express-checkout-element"
						style={ { display: 'none' } }
					/>
				</div>
			</div>
		);

		stripeElementMock = {
			mount: jest.fn(),
			unmount: jest.fn(),
			on: jest.fn( ( event, callback ) => {
				stripeElementMock[ `__triggerEvent-${ event }` ] = callback;
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

	afterEach( () => {
		delete global.Stripe;
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
		).not.toBeVisible();
	} );

	it( 'should not initialize Stripe if the total amount is 0', async () => {
		global.wcpayExpressCheckoutParams.product.total.amount = 0;
		global.wcpayExpressCheckoutParams.product.displayItems = [];
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		expect( global.Stripe ).not.toHaveBeenCalled();
		expect( recordUserEvent ).not.toHaveBeenCalled();
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).not.toBeVisible();
	} );

	it( 'should not track the initialization if no payment methods are available', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		expect( global.Stripe ).toHaveBeenCalled();
		expect( stripeInstance.elements ).toHaveBeenCalledWith( {
			mode: 'payment',
			amount: 1100,
			currency: 'usd',
			paymentMethodCreation: 'manual',
			appearance: expect.anything(),
			locale: 'it',
		} );

		// triggering the `ready` event on the ECE button, to test its callback.
		stripeElementMock[ `__triggerEvent-ready` ]( {
			availablePaymentMethods: {
				link: false,
				applePay: false,
				googlePay: false,
				paypal: false,
				amazonPay: false,
			},
		} );

		expect( recordUserEvent ).not.toHaveBeenCalled();
	} );

	it( 'should track the initialization', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		expect( global.Stripe ).toHaveBeenCalled();
		expect( stripeInstance.elements ).toHaveBeenCalledWith( {
			mode: 'payment',
			amount: 1100,
			currency: 'usd',
			paymentMethodCreation: 'manual',
			appearance: expect.anything(),
			locale: 'it',
		} );

		// triggering the `ready` event on the ECE button, to test its callback.
		stripeElementMock[ `__triggerEvent-ready` ]( {
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
			expect.objectContaining( { source: 'product' } )
		);
		expect( recordUserEvent ).toHaveBeenNthCalledWith(
			2,
			'gpay_button_load',
			expect.objectContaining( { source: 'product' } )
		);
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).toBeVisible();
	} );
} );
