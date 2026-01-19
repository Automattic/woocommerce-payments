/**
 * External dependencies
 */
import { render, screen, waitFor } from '@testing-library/react';
import $ from 'jquery';
import { recordUserEvent } from 'tracks';
import { rest } from 'msw';

/**
 * Internal dependencies
 */
import { server } from 'jest-utils/msw-server';

jest.mock( 'tracks', () => ( {
	recordUserEvent: jest.fn(),
} ) );
jest.mock( 'lodash', () => ( {
	debounce: jest.fn( ( callback ) => callback ),
} ) );

describe( 'Tokenized Express Checkout Element - Pay-for-order page logic', () => {
	let stripeElementMock, stripeInstance;
	const requestListener = jest.fn().mockReturnValue( null );

	beforeEach( () => {
		requestListener.mockClear();
		server.events.on( 'request:start', requestListener );
		server.use(
			rest.get( '/wc/store/v1/order/:id', ( req, res, ctx ) => {
				return res(
					ctx.json( {
						id: req.params.id,
						status: 'pending',
						items: [
							{
								key: 'wc_order_fAk3nUmB3R',
								id: 1,
								quantity: 2,
								name: 'Cap',
								sku: 'woo-cap',
								prices: {
									price: '1500',
									regular_price: '1700',
									sale_price: '1500',
									price_range: null,
									currency_code: 'EUR',
									currency_symbol: '\u20ac',
									currency_minor_unit: 2,
									currency_decimal_separator: ',',
									currency_thousand_separator: '.',
									currency_prefix: '\u20ac',
									currency_suffix: '',
									raw_prices: {
										precision: 6,
										price: '15000000',
										regular_price: '17000000',
										sale_price: '15000000',
									},
								},
								totals: {
									line_subtotal: '3200',
									line_subtotal_tax: '160',
									line_total: '3200',
									line_total_tax: '160',
									currency_code: 'EUR',
									currency_symbol: '\u20ac',
									currency_minor_unit: 2,
									currency_decimal_separator: ',',
									currency_thousand_separator: '.',
									currency_prefix: '\u20ac',
									currency_suffix: '',
								},
							},
							{
								key: 'wc_order_fAk3nUmB3R',
								id: 2,
								quantity: 1,
								name: 'T-Shirt',
								sku: 'woo-tshirt',
								prices: {
									price: '1700',
									regular_price: '1700',
									sale_price: '1700',
									price_range: null,
									currency_code: 'EUR',
									currency_symbol: '\u20ac',
									currency_minor_unit: 2,
									currency_decimal_separator: ',',
									currency_thousand_separator: '.',
									currency_prefix: '\u20ac',
									currency_suffix: '',
									raw_prices: {
										precision: 6,
										price: '17000000',
										regular_price: '17000000',
										sale_price: '17000000',
									},
								},
								totals: {
									line_subtotal: '1800',
									line_subtotal_tax: '90',
									line_total: '1800',
									line_total_tax: '90',
									currency_code: 'EUR',
									currency_symbol: '\u20ac',
									currency_minor_unit: 2,
									currency_decimal_separator: ',',
									currency_thousand_separator: '.',
									currency_prefix: '\u20ac',
									currency_suffix: '',
								},
								catalog_visibility: 'visible',
							},
						],
						coupons: [],
						fees: [
							{
								key: 3,
								name: '$10.00 fee',
								totals: {
									total: '1000',
									total_tax: '50',
									currency_code: 'EUR',
									currency_symbol: '\u20ac',
									currency_minor_unit: 2,
									currency_decimal_separator: ',',
									currency_thousand_separator: '.',
									currency_prefix: '\u20ac',
									currency_suffix: '',
								},
							},
						],
						totals: {
							subtotal: '5000',
							total_discount: '0',
							total_shipping: '200',
							total_fees: '1000',
							total_tax: '310',
							total_refund: '0',
							total_price: '6510',
							total_items: '5000',
							total_items_tax: '300',
							total_fees_tax: '50',
							total_discount_tax: '0',
							total_shipping_tax: '10',
							tax_lines: [
								{
									name: 'Global Tax rate',
									price: '300',
									rate: '5',
								},
							],
							currency_code: 'EUR',
							currency_symbol: '\u20ac',
							currency_minor_unit: 2,
							currency_decimal_separator: ',',
							currency_thousand_separator: '.',
							currency_prefix: '\u20ac',
							currency_suffix: '',
						},
						shipping_address: {
							first_name: 'Fake',
							last_name: 'User',
							company: '',
							address_1: '',
							address_2: '',
							city: 'Bruges',
							state: '',
							postcode: '8000',
							country: 'BE',
							phone: '',
						},
						billing_address: {
							first_name: 'Fake',
							last_name: 'User',
							company: '',
							address_1: '',
							address_2: '',
							city: 'Bruges',
							state: '',
							postcode: '8000',
							country: 'BE',
							email: 'cheese@toast.com',
							phone: '',
						},
						needs_payment: true,
						needs_shipping: true,
						errors: [],
					} )
				);
			} )
		);
		// ensuring jQuery is available globally.
		global.$ = global.jQuery = $;
		// ensuring that `callback` is immediately invoked on document.ready.
		$.fn.ready = ( callback ) => callback( $ );
		global.jQuery.blockUI = () => null;
		global.jQuery.unblockUI = () => null;

		global.wcpayConfig = {
			order_id: 999,
			key: 'wc_order_fAk3nUmB3R',
			billing_email: 'cheese@toast.com',
		};
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
			currency_decimals: 2,
			allowed_shipping_countries: [ 'US' ],
		};
		global.wcpayExpressCheckoutParams.store_name = 'My fancy store';
		global.wcpayExpressCheckoutParams.button_context = 'pay_for_order';

		// just mocking some server-side-provided DOM elements.
		render(
			<div>
				<div className="woocommerce-notices-wrapper" />
				<div id="wcpay-express-checkout-wrapper">
					<div
						id="wcpay-express-checkout-element"
						data-testid="wcpay-express-checkout-element"
						style={ { display: 'none' } }
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

	afterEach( () => {
		delete global.Stripe;
		server.events.removeListener( 'request:start', requestListener );
		server.resetHandlers();
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
		expect( requestListener ).not.toHaveBeenCalled();
	} );

	it( 'should track the initialization', async () => {
		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		// waiting for the API call to be completed.
		await waitFor( () => expect( requestListener ).toHaveBeenCalled() );

		expect( requestListener ).toHaveBeenCalled();
		expect( requestListener ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				method: 'GET',
				// `url` is an URL object, so it's kinda nested
				url: expect.objectContaining( {
					href: expect.stringContaining(
						'/wc/store/v1/order/999?key=wc_order_fAk3nUmB3R&billing_email=cheese%40toast.com'
					),
				} ),
			} )
		);

		expect( global.Stripe ).toHaveBeenCalled();
		expect( stripeInstance.elements ).toHaveBeenCalledWith( {
			mode: 'payment',
			amount: 6510,
			currency: 'eur',
			appearance: expect.anything(),
			locale: 'it',
			paymentMethodTypes: [ 'card' ],
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
			expect.objectContaining( { source: 'pay_for_order' } )
		);
		expect( recordUserEvent ).toHaveBeenNthCalledWith(
			2,
			'gpay_button_load',
			expect.objectContaining( { source: 'pay_for_order' } )
		);
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).toBeVisible();
	} );
} );
