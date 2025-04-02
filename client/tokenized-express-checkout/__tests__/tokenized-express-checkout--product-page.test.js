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
	default: jest.fn( () => Promise.resolve() ),
} ) );

describe( 'Tokenized Express Checkout Element - Product page logic', () => {
	let stripeElementMock, stripeInstance;
	beforeEach( () => {
		apiFetch.mockReset();
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
		global.wcpayExpressCheckoutParams.button_context = 'product';
		global.wcpayExpressCheckoutParams.product = {
			product_type: 'simple',
			needs_shipping: true,
			country_code: 'US',
			currency: 'usd',
			total: { amount: 1100 },
			shippingOptions: {
				id: 'pending',
				label: 'Pending server-side',
				detail: '',
				amount: 0,
			},
			displayItems: [
				{
					label: 'Beanie server-side',
					amount: 1000,
				},
				{
					label: 'Tax server-side',
					amount: 100,
					pending: false,
				},
				{
					label: 'Shipping server-side',
					amount: 0,
					pending: true,
				},
			],
		};

		// just mocking some server-side-provided DOM elements.
		render(
			<div>
				<div className="woocommerce-notices-wrapper" />
				<button className="single_add_to_cart_button" value="333">
					Fake button
				</button>
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
		).not.toHaveClass( 'is-ready' );
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
		stripeElementMock.__getRegisteredEvent( 'ready' )( {
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

	it( 'should unmount and show an error message if a network issue happens after clicking the button', async () => {
		apiFetch.mockImplementation( async ( { path, method } ) => {
			if (
				path.includes( '/wc/store/v1/cart/add-item' ) &&
				method === 'POST'
			) {
				return Promise.reject();
			}

			if ( path.includes( '/wc/store/v1/cart' ) && method === 'GET' ) {
				return Promise.resolve( {
					json: () => Promise.resolve( { items: [] } ),
					headers: new Map(),
				} );
			}

			if ( path.includes( '/wc/store/v1/cart/remove-item' ) ) {
				return Promise.resolve( {
					json: () => Promise.resolve( { items: [] } ),
				} );
			}

			return Promise.reject();
		} );

		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		// triggering the `click` event on the ECE button, to test its callback.
		const clickEventResolveMock = jest.fn();
		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: clickEventResolveMock,
			expressPaymentType: 'google_pay',
		} );

		expect( recordUserEvent ).toHaveBeenCalledWith(
			'gpay_button_click',
			expect.objectContaining( { source: 'product' } )
		);
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).toBeVisible();
		expect( clickEventResolveMock ).toHaveBeenCalledWith( {
			allowedShippingCountries: [ 'US' ],
			business: { name: 'My fancy store' },
			emailRequired: true,
			lineItems: [
				{ amount: 1000, name: 'Beanie server-side' },
				{ amount: 100, name: 'Tax server-side' },
				{ amount: 0, name: 'Shipping server-side' },
			],
			phoneNumberRequired: false,
			shippingAddressRequired: true,
			shippingRates: [
				{ amount: 0, displayName: 'Pending', id: 'pending' },
			],
		} );

		await waitFor( () =>
			expect( stripeElementMock.unmount ).toHaveBeenCalled()
		);

		expect(
			screen.getByText(
				'There was an error processing the product with this payment method. Please add the product to the cart, instead.'
			)
		).toBeInTheDocument();
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).not.toBeVisible();

		// also ensuring that "confirm" doesn't trigger any other payment processing logic, if the "add to cart" failed.
		stripeElementMock.__getRegisteredEvent( 'confirm' )( {} );

		expect( stripeElementMock.submit ).not.toHaveBeenCalled();
	} );

	it( 'should provide fallback shipping rates on click', async () => {
		apiFetch.mockImplementation( async () => {
			return Promise.resolve( {
				json: () => Promise.resolve( { items: [] } ),
				headers: new Map(),
			} );
		} );

		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		// triggering the `click` event on the ECE button, to test its callback.
		const clickEventResolveMock = jest.fn();
		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: clickEventResolveMock,
			expressPaymentType: 'google_pay',
		} );

		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).toBeVisible();
		expect( clickEventResolveMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				lineItems: [
					{ amount: 1000, name: 'Beanie server-side' },
					{ amount: 100, name: 'Tax server-side' },
					{ amount: 0, name: 'Shipping server-side' },
				],
				shippingAddressRequired: true,
				shippingRates: [
					{
						amount: 0,
						displayName: 'Pending',
						id: 'pending',
					},
				],
			} )
		);
	} );

	it( 'should provide real shipping rates on click', async () => {
		global.wcpayExpressCheckoutParams.product = undefined;
		apiFetch.mockImplementation( async ( { path } ) => {
			if ( path.includes( '/wc/store/v1/cart/remove-item' ) ) {
				return Promise.resolve( {
					json: () => Promise.resolve( { items: [] } ),
				} );
			}

			return Promise.resolve( {
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
			} );
		} );

		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		// waiting for the API call to be completed.
		await waitFor( () => expect( apiFetch ).toHaveBeenCalled() );

		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				headers: expect.objectContaining( {
					'X-WooPayments-Tokenized-Cart-Is-Ephemeral-Cart': '1',
				} ),
			} )
		);

		// triggering the `click` event on the ECE button, to test its callback.
		const clickEventResolveMock = jest.fn();
		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: clickEventResolveMock,
			expressPaymentType: 'google_pay',
		} );

		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				headers: expect.not.objectContaining( {
					'X-WooPayments-Tokenized-Cart-Is-Ephemeral-Cart': '1',
				} ),
			} )
		);
		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).toBeVisible();
		expect( clickEventResolveMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				lineItems: [
					{ amount: 2399, name: 'Beanie' },
					{ amount: 198, name: 'Tax' },
					{ amount: 1100, name: 'Shipping' },
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
		apiFetch.mock.calls.forEach( ( [ apiFetchArguments ] ) => {
			// based on the sequence above, every single call should be an "add item", never "remove"
			expect( apiFetchArguments.path ).toContain(
				'/wc/store/v1/cart/add-item'
			);
			expect( apiFetchArguments.path ).not.toContain(
				'/wc/store/v1/cart/remove-item'
			);
		} );

		// at this point, if `cancel` is called, the item should be removed from the cart.
		stripeElementMock.__getRegisteredEvent( 'cancel' )();

		await waitFor( () => expect( apiFetch ).toHaveBeenCalled() );
		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				path: expect.stringContaining(
					'/wc/store/v1/cart/remove-item'
				),
			} )
		);
	} );

	it( 'should not provide shipping rates when the shipping address is not needed', async () => {
		global.wcpayExpressCheckoutParams.product.shippingOptions = undefined;
		global.wcpayExpressCheckoutParams.product.needs_shipping = false;
		global.wcpayExpressCheckoutParams.product.displayItems = [
			{
				label: 'Beanie',
				amount: 1000,
			},
			{
				label: 'Tax',
				amount: 100,
				pending: false,
			},
		];

		apiFetch.mockImplementation( async () => {
			return Promise.resolve( {
				json: () => Promise.resolve( { items: [] } ),
				headers: new Map(),
			} );
		} );

		await jest.isolateModulesAsync( async () => {
			await import( '..' );
		} );

		// triggering the `click` event on the ECE button, to test its callback.
		const clickEventResolveMock = jest.fn();
		stripeElementMock.__getRegisteredEvent( 'click' )( {
			resolve: clickEventResolveMock,
			expressPaymentType: 'google_pay',
		} );

		expect(
			screen.getByTestId( 'wcpay-express-checkout-element' )
		).toBeVisible();
		expect( clickEventResolveMock ).toHaveBeenCalledWith(
			expect.objectContaining( {
				lineItems: [
					{ amount: 1000, name: 'Beanie' },
					{ amount: 100, name: 'Tax' },
				],
				shippingAddressRequired: false,
				shippingRates: undefined,
			} )
		);
	} );
} );
