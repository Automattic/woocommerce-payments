/**
 * External dependencies
 */
import { render, screen, waitFor } from '@testing-library/react';
import $ from 'jquery';
import { recordUserEvent } from 'tracks';
import apiFetch from '@wordpress/api-fetch';

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
				json: () =>
					Promise.resolve( {
						needs_shipping: true,
						totals: {
							total_items: '2399',
							total_items_tax: '198',
							total_fees: '0',
							total_fees_tax: '0',
							total_discount: '0',
							total_discount_tax: '0',
							total_shipping: '1100',
							total_shipping_tax: '0',
							total_price: '3697',
							total_tax: '198',
							tax_lines: [
								{
									name: 'US-CA Tax rate',
									price: '198',
									rate: '8.25%',
								},
							],
							currency_code: 'USD',
							currency_symbol: '$',
							currency_minor_unit: 2,
						},
						shipping_rates: [
							{
								package_id: 0,
								name: 'Shipment 1',
								shipping_rates: [
									{
										meta_data: [],
										rate_id: 'flat_rate:1',
										name: 'Flat rate',
										description: '',
										price: '1100',
										taxes: '0',
										instance_id: 1,
										method_id: 'flat_rate',
										selected: true,
										currency_minor_unit: 2,
									},
									{
										meta_data: [],
										rate_id: 'flat_rate:5',
										name: 'Express shipping',
										description: '',
										price: '2200',
										taxes: '0',
										instance_id: 5,
										method_id: 'flat_rate',
										selected: false,
										currency_minor_unit: 2,
									},
								],
							},
						],
						items: [
							{
								key: 'aab3238922bcc25a6f606eb525ffdc56',
								id: 14,
								type: 'simple',
								quantity: 1,
								name: 'Beanie',
								sku: 'woo-beanie',
								images: [],
								variation: [],
								item_data: [],
								prices: {
									price: '2399',
									regular_price: '2399',
									sale_price: '2399',
									price_range: null,
									currency_code: 'USD',
									currency_minor_unit: 2,
								},
								totals: {
									line_subtotal: '2399',
									line_subtotal_tax: '198',
									line_total: '2399',
									line_total_tax: '198',
									currency_code: 'USD',
									currency_minor_unit: 2,
								},
							},
						],
					} ),
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
				json: () =>
					Promise.resolve( {
						items: [
							{
								key: 'aab3238922bcc25a6f606eb525ffdc56',
								id: 14,
								type: 'simple',
								quantity: 1,
								name: 'Beanie',
								sku: 'woo-beanie',
								images: [],
								variation: [],
								item_data: [],
								prices: {
									price: '2399',
									regular_price: '2399',
									sale_price: '2399',
									price_range: null,
									currency_code: 'USD',
									currency_symbol: '$',
									currency_minor_unit: 2,
									currency_decimal_separator: '.',
									currency_thousand_separator: ',',
									currency_prefix: '$',
									currency_suffix: '',
									raw_prices: {
										precision: 6,
										price: '23990000',
										regular_price: '23990000',
										sale_price: '23990000',
									},
								},
								totals: {
									line_subtotal: '2399',
									line_subtotal_tax: '120',
									line_total: '0',
									line_total_tax: '0',
									currency_code: 'USD',
									currency_symbol: '$',
									currency_minor_unit: 2,
									currency_decimal_separator: '.',
									currency_thousand_separator: ',',
									currency_prefix: '$',
									currency_suffix: '',
								},
							},
						],
						coupons: [
							{
								code: 'allfree',
								discount_type: 'percent',
								totals: {
									total_discount: '1200',
									total_discount_tax: '60',
									currency_code: 'USD',
									currency_symbol: '$',
									currency_minor_unit: 2,
									currency_decimal_separator: '.',
									currency_thousand_separator: ',',
									currency_prefix: '$',
									currency_suffix: '',
								},
							},
						],
						fees: [],
						totals: {
							total_items: '2399',
							total_items_tax: '120',
							total_fees: '0',
							total_fees_tax: '0',
							total_discount: '2399',
							total_discount_tax: '120',
							total_shipping: '0',
							total_shipping_tax: '0',
							total_price: '0',
							total_tax: '0',
							tax_lines: [],
							currency_code: 'USD',
							currency_symbol: '$',
							currency_minor_unit: 2,
							currency_decimal_separator: '.',
							currency_thousand_separator: ',',
							currency_prefix: '$',
							currency_suffix: '',
						},
						needs_payment: false,
						needs_shipping: true,
						has_calculated_shipping: true,
						shipping_rates: [
							{
								package_id: 0,
								shipping_rates: [
									{
										rate_id: 'free_shipping:9',
										name: 'Free shipping',
										description: '',
										delivery_time: '',
										price: '0',
										taxes: '0',
										instance_id: 9,
										method_id: 'free_shipping',
										meta_data: [
											{
												key: 'Items',
												value: 'Cap &times; 1',
											},
										],
										selected: true,
										currency_code: 'USD',
										currency_symbol: '$',
										currency_minor_unit: 2,
										currency_decimal_separator: '.',
										currency_thousand_separator: ',',
										currency_prefix: '$',
										currency_suffix: '',
									},
									{
										rate_id: 'flat_rate:2',
										name: 'Flat rate',
										description: '',
										delivery_time: '',
										price: '1170',
										taxes: '59',
										instance_id: 2,
										method_id: 'flat_rate',
										meta_data: [
											{
												key: 'Items',
												value: 'Cap &times; 1',
											},
										],
										selected: false,
										currency_code: 'USD',
										currency_symbol: '$',
										currency_minor_unit: 2,
										currency_decimal_separator: '.',
										currency_thousand_separator: ',',
										currency_prefix: '$',
										currency_suffix: '',
									},
								],
							},
						],
					} ),
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
