/**
 * External dependencies
 */
import { addFilter, removeFilter } from '@wordpress/hooks';
import { recordUserEvent } from 'tracks';

/**
 * Internal dependencies
 */
import {
	shippingAddressChangeHandler,
	shippingRateChangeHandler,
	onConfirmHandler,
	setCartApiHandler,
} from '../event-handlers';
import {
	rememberElementCurrency,
	__resetElementCurrencyForTests,
} from '../utils/element-currency-cache';

jest.mock( 'tracks', () => ( {
	recordUserEvent: jest.fn(),
} ) );

describe( 'Express checkout event handlers', () => {
	let cartApiUpdateCustomerMock;
	let cartApiPlaceOrderMock;
	let cartApiSelectShippingRateMock;
	beforeEach( () => {
		cartApiUpdateCustomerMock = jest.fn();
		cartApiPlaceOrderMock = jest.fn();
		cartApiSelectShippingRateMock = jest.fn();
		global.window.wcpayFraudPreventionToken = 'token123';
		global.wcpayExpressCheckoutParams = {};
		global.wcpayExpressCheckoutParams.checkout = {
			display_prices_with_tax: false,
		};
		global.wcpayExpressCheckoutParams.flags = {
			isEceUsingConfirmationTokens: true,
		};
		global.wcpayExpressCheckoutParams.enabled_methods = [
			'payment_request',
		];
		global.wcpayExpressCheckoutParams.has_subscription = false;

		setCartApiHandler( {
			updateCustomer: cartApiUpdateCustomerMock,
			selectShippingRate: cartApiSelectShippingRateMock,
			placeOrder: cartApiPlaceOrderMock,
		} );
	} );

	describe( 'shippingAddressChangeHandler', () => {
		let event;
		let elements;

		beforeEach( () => {
			event = {
				name: 'John Doe',
				address: {
					line1: '123 Main St',
					city: 'New York',
					state: 'NY',
					country: 'US',
					postal_code: '10001',
				},
				resolve: jest.fn(),
				reject: jest.fn(),
			};
			elements = {
				update: jest.fn( () => Promise.resolve() ),
			};
		} );

		afterEach( () => {
			jest.clearAllMocks();
			__resetElementCurrencyForTests();
		} );

		it( 'should handle successful response', async () => {
			cartApiUpdateCustomerMock.mockResolvedValue( {
				items: [],
				shipping_rates: [
					{
						package_id: 0,
						name: 'Shipment 1',
						destination: {},
						items: [
							{
								key: 'aab3238922bcc25a6f606eb525ffdc56',
								name: 'Beanie',
								quantity: 2,
							},
						],
						shipping_rates: [
							{
								rate_id: 'flat_rate:14',
								name: 'Standard Shipping',
								description: '',
								delivery_time: '',
								price: '1000',
								taxes: '300',
								instance_id: 14,
								method_id: 'flat_rate',
								meta_data: [
									{
										key: 'Items',
										value: 'Beanie &times; 2',
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
						],
					},
				],
				totals: {
					total_price: 1000,
					currency_minor_unit: 2,
					currency_code: 'USD',
					currency_symbol: '$',
					currency_decimal_separator: '.',
					currency_thousand_separator: ',',
					currency_prefix: '$',
					currency_suffix: '',
				},
			} );

			await shippingAddressChangeHandler( event, elements );

			expect( cartApiUpdateCustomerMock ).toHaveBeenCalledWith( {
				shipping_address: expect.objectContaining( {
					first_name: 'John',
					last_name: 'Doe',
					company: '',
					address_1: '123 Main St',
					address_2: '',
					city: 'New York',
					state: 'NY',
					postcode: '10001',
					country: 'US',
				} ),
			} );

			expect( elements.update ).toHaveBeenCalledWith( {
				amount: 1000,
				setupFutureUsage: null,
			} );
			expect( event.resolve ).toHaveBeenCalledWith( {
				shippingRates: [
					expect.objectContaining( {
						id: 'flat_rate:14',
						displayName: 'Standard Shipping',
						amount: 1000,
						deliveryEstimate: '',
					} ),
				],
				lineItems: [],
			} );
			expect( event.reject ).not.toHaveBeenCalled();
		} );

		it( 'should update setupFutureUsage when cart contains a subscription', async () => {
			cartApiUpdateCustomerMock.mockResolvedValue( {
				items: [],
				extensions: {
					subscriptions: [
						{
							billing_period: 'month',
							billing_interval: 1,
							totals: { total_price: '1000' },
						},
					],
				},
				shipping_rates: [
					{
						package_id: 0,
						name: 'Shipment 1',
						destination: {},
						items: [],
						shipping_rates: [
							{
								rate_id: 'flat_rate:14',
								name: 'Standard Shipping',
								description: '',
								delivery_time: '',
								price: '1000',
								taxes: '0',
								meta_data: [],
								selected: true,
								currency_minor_unit: 2,
							},
						],
					},
				],
				totals: {
					total_price: 1000,
					currency_minor_unit: 2,
				},
			} );

			await shippingAddressChangeHandler( event, elements );

			expect( elements.update ).toHaveBeenCalledWith( {
				amount: 1000,
				setupFutureUsage: 'off_session',
			} );
		} );

		it( 'should handle displaying prices inclusive of tax', async () => {
			global.wcpayExpressCheckoutParams.checkout.display_prices_with_tax = true;

			cartApiUpdateCustomerMock.mockResolvedValue( {
				items: [],
				shipping_rates: [
					{
						package_id: 0,
						name: 'Shipment 1',
						destination: {},
						items: [
							{
								key: 'aab3238922bcc25a6f606eb525ffdc56',
								name: 'Beanie',
								quantity: 2,
							},
						],
						shipping_rates: [
							{
								rate_id: 'flat_rate:14',
								name: 'Standard Shipping',
								description: '',
								delivery_time: '',
								price: '1000',
								taxes: '300',
								instance_id: 14,
								method_id: 'flat_rate',
								meta_data: [
									{
										key: 'Items',
										value: 'Beanie &times; 2',
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
						],
					},
				],
				totals: {
					total_price: 1000,
					currency_minor_unit: 2,
					currency_code: 'USD',
					currency_symbol: '$',
					currency_decimal_separator: '.',
					currency_thousand_separator: ',',
					currency_prefix: '$',
					currency_suffix: '',
				},
			} );

			await shippingAddressChangeHandler( event, elements );

			expect( cartApiUpdateCustomerMock ).toHaveBeenCalledWith( {
				shipping_address: expect.objectContaining( {
					first_name: 'John',
					last_name: 'Doe',
					company: '',
					address_1: '123 Main St',
					address_2: '',
					city: 'New York',
					state: 'NY',
					postcode: '10001',
					country: 'US',
				} ),
			} );

			expect( elements.update ).toHaveBeenCalledWith( {
				amount: 1000,
				setupFutureUsage: null,
			} );
			expect( event.resolve ).toHaveBeenCalledWith( {
				shippingRates: [
					expect.objectContaining( {
						id: 'flat_rate:14',
						displayName: 'Standard Shipping',
						amount: 1300,
						deliveryEstimate: '',
					} ),
				],
				lineItems: [],
			} );
			expect( event.reject ).not.toHaveBeenCalled();
		} );

		it( 'should handle zero rates in the response', async () => {
			cartApiUpdateCustomerMock.mockResolvedValue( {
				items: [],
				shipping_rates: [
					{
						package_id: 0,
						name: 'Shipment 1',
						destination: {},
						items: [
							{
								key: 'aab3238922bcc25a6f606eb525ffdc56',
								name: 'Beanie',
								quantity: 2,
							},
						],
						shipping_rates: [],
					},
				],
				totals: {
					total_price: 1000,
					currency_minor_unit: 2,
					currency_code: 'USD',
					currency_symbol: '$',
					currency_decimal_separator: '.',
					currency_thousand_separator: ',',
					currency_prefix: '$',
					currency_suffix: '',
				},
			} );

			await shippingAddressChangeHandler( event, elements );

			expect( cartApiUpdateCustomerMock ).toHaveBeenCalled();

			expect( elements.update ).not.toHaveBeenCalled();
			expect( event.resolve ).not.toHaveBeenCalled();
			expect( event.reject ).toHaveBeenCalled();
		} );

		it( 'should handle unsuccessful response', async () => {
			cartApiUpdateCustomerMock.mockRejectedValue( {} );

			await shippingAddressChangeHandler( event, elements );

			expect( cartApiUpdateCustomerMock ).toHaveBeenCalled();

			expect( elements.update ).not.toHaveBeenCalled();
			expect( event.resolve ).not.toHaveBeenCalled();
			expect( event.reject ).toHaveBeenCalled();
		} );

		it( 'rejects the address and surfaces a message when the cart currency drifts from the element currency', async () => {
			rememberElementCurrency( 'usd' );
			const showError = jest.fn();

			// A country-based multi-currency plugin flipped the cart to EUR
			// after the wallet sheet opened in USD.
			cartApiUpdateCustomerMock.mockResolvedValue( {
				items: [],
				shipping_rates: [],
				totals: {
					total_price: 1000,
					currency_minor_unit: 2,
					currency_code: 'EUR',
				},
			} );

			await shippingAddressChangeHandler( event, elements, showError );

			expect( event.reject ).toHaveBeenCalled();
			expect( event.resolve ).not.toHaveBeenCalled();
			expect( elements.update ).not.toHaveBeenCalled();
			expect( showError ).toHaveBeenCalledWith(
				expect.stringContaining( 'USD' )
			);
			expect( showError ).toHaveBeenCalledWith(
				expect.stringContaining( 'EUR' )
			);
		} );

		it( 'proceeds normally when the element currency matches the cart currency', async () => {
			rememberElementCurrency( 'usd' );

			cartApiUpdateCustomerMock.mockResolvedValue( {
				items: [],
				shipping_rates: [
					{
						package_id: 0,
						name: 'Shipment 1',
						destination: {},
						items: [],
						shipping_rates: [
							{
								rate_id: 'flat_rate:14',
								name: 'Standard Shipping',
								description: '',
								delivery_time: '',
								price: '1000',
								taxes: '0',
								meta_data: [],
								selected: true,
								currency_minor_unit: 2,
								currency_code: 'USD',
							},
						],
					},
				],
				totals: {
					total_price: 1000,
					currency_minor_unit: 2,
					currency_code: 'USD',
				},
			} );

			await shippingAddressChangeHandler( event, elements );

			expect( event.reject ).not.toHaveBeenCalled();
			expect( event.resolve ).toHaveBeenCalled();
		} );

		describe( 'when wallet address triggers Local Pickup → delivery switch', () => {
			const buildCart = (
				shippingRates,
				totalPrice = 1000,
				selectedRateOrigin
			) => ( {
				items: [],
				shipping_rates: [
					{
						package_id: 0,
						name: 'Shipment 1',
						destination: {},
						items: [],
						shipping_rates: shippingRates,
						...( selectedRateOrigin !== undefined
							? { selected_rate_origin: selectedRateOrigin }
							: {} ),
					},
				],
				totals: {
					total_price: totalPrice,
					currency_minor_unit: 2,
					currency_code: 'USD',
					currency_symbol: '$',
					currency_decimal_separator: '.',
					currency_thousand_separator: ',',
					currency_prefix: '$',
					currency_suffix: '',
				},
			} );

			const baseRate = {
				description: '',
				delivery_time: '',
				taxes: '0',
				meta_data: [],
				currency_code: 'USD',
				currency_symbol: '$',
				currency_minor_unit: 2,
				currency_decimal_separator: '.',
				currency_thousand_separator: ',',
				currency_prefix: '$',
				currency_suffix: '',
			};

			const pickupRate = {
				...baseRate,
				rate_id: 'pickup_location:0',
				name: 'Local Pickup',
				price: '0',
				instance_id: 0,
				method_id: 'pickup_location',
				selected: true,
			};

			const deliveryRate = {
				...baseRate,
				rate_id: 'flat_rate:14',
				name: 'Standard Shipping',
				price: '1000',
				instance_id: 14,
				method_id: 'flat_rate',
				selected: false,
			};

			it( 'reselects the first delivery rate when Local Pickup is stuck as selected', async () => {
				cartApiUpdateCustomerMock.mockResolvedValue(
					buildCart( [ pickupRate, deliveryRate ], 0 )
				);
				cartApiSelectShippingRateMock.mockResolvedValue(
					buildCart(
						[
							{ ...pickupRate, selected: false },
							{ ...deliveryRate, selected: true },
						],
						1000
					)
				);

				await shippingAddressChangeHandler( event, elements );

				expect( cartApiSelectShippingRateMock ).toHaveBeenCalledWith( {
					package_id: 0,
					rate_id: 'flat_rate:14',
				} );
				expect( elements.update ).toHaveBeenCalledWith( {
					amount: 1000,
					setupFutureUsage: null,
				} );
				expect( event.resolve ).toHaveBeenCalledWith(
					expect.objectContaining( {
						shippingRates: [
							expect.objectContaining( {
								id: 'flat_rate:14',
							} ),
							expect.objectContaining( {
								id: 'pickup_location:0',
							} ),
						],
					} )
				);
			} );

			it( 'leaves the selection alone when only Local Pickup is available', async () => {
				cartApiUpdateCustomerMock.mockResolvedValue(
					buildCart( [ pickupRate ], 0 )
				);

				await shippingAddressChangeHandler( event, elements );

				expect( cartApiSelectShippingRateMock ).not.toHaveBeenCalled();
				expect( event.resolve ).toHaveBeenCalledWith(
					expect.objectContaining( {
						shippingRates: [
							expect.objectContaining( {
								id: 'pickup_location:0',
							} ),
						],
					} )
				);
			} );

			it( 'leaves the selection alone when a delivery rate is already selected', async () => {
				cartApiUpdateCustomerMock.mockResolvedValue(
					buildCart(
						[
							{ ...pickupRate, selected: false },
							{ ...deliveryRate, selected: true },
						],
						1000
					)
				);

				await shippingAddressChangeHandler( event, elements );

				expect( cartApiSelectShippingRateMock ).not.toHaveBeenCalled();
				expect( event.reject ).not.toHaveBeenCalled();
				expect( elements.update ).toHaveBeenCalledWith( {
					amount: 1000,
					setupFutureUsage: null,
				} );
				expect( event.resolve ).toHaveBeenCalledWith(
					expect.objectContaining( {
						// Both rates remain in the package; the transformer
						// sorts the selected one (Flat Rate) first.
						shippingRates: expect.arrayContaining( [
							expect.objectContaining( {
								id: 'flat_rate:14',
							} ),
							expect.objectContaining( {
								id: 'pickup_location:0',
							} ),
						] ),
					} )
				);
			} );

			it( 'falls back to the original cart data if reselect fails', async () => {
				// Suppress the expected console.warn from the catch block so the
				// test output stays clean; we still assert it was called below.
				const consoleWarnSpy = jest
					.spyOn( console, 'warn' )
					.mockImplementation( () => {} );
				cartApiUpdateCustomerMock.mockResolvedValue(
					buildCart( [ pickupRate, deliveryRate ], 0 )
				);
				cartApiSelectShippingRateMock.mockRejectedValue(
					new Error( 'network' )
				);

				await shippingAddressChangeHandler( event, elements );

				expect( cartApiSelectShippingRateMock ).toHaveBeenCalled();
				expect( event.reject ).not.toHaveBeenCalled();
				expect( event.resolve ).toHaveBeenCalledWith(
					expect.objectContaining( {
						// Original cart from updateCustomer still has the
						// pickup rate marked selected; transformer sorts it first.
						shippingRates: expect.arrayContaining( [
							expect.objectContaining( {
								id: 'pickup_location:0',
							} ),
							expect.objectContaining( {
								id: 'flat_rate:14',
							} ),
						] ),
					} )
				);
				expect( consoleWarnSpy ).toHaveBeenCalledWith(
					expect.stringContaining(
						'preferDeliveryOverLocalPickup failed'
					),
					expect.any( Error )
				);
				// The failure must also be observable in production
				// monitoring, not just the shopper's browser console.
				expect( recordUserEvent ).toHaveBeenCalledWith(
					'express_checkout_pickup_reselect_failed'
				);

				consoleWarnSpy.mockRestore();
			} );

			it( 'leaves a manually chosen Local Pickup selected (selected_rate_origin: manual)', async () => {
				cartApiUpdateCustomerMock.mockResolvedValue(
					buildCart( [ pickupRate, deliveryRate ], 0, 'manual' )
				);

				await shippingAddressChangeHandler( event, elements );

				expect( cartApiSelectShippingRateMock ).not.toHaveBeenCalled();
				expect( event.resolve ).toHaveBeenCalledWith(
					expect.objectContaining( {
						shippingRates: [
							expect.objectContaining( {
								id: 'pickup_location:0',
							} ),
							expect.objectContaining( { id: 'flat_rate:14' } ),
						],
					} )
				);
				// The shopper chose free pickup — the charged amount must stay 0.
				expect( elements.update ).toHaveBeenCalledWith(
					expect.objectContaining( { amount: 0 } )
				);
			} );

			it( 'reselects delivery when the pickup was auto-defaulted (selected_rate_origin: auto)', async () => {
				cartApiUpdateCustomerMock.mockResolvedValue(
					buildCart( [ pickupRate, deliveryRate ], 0, 'auto' )
				);
				cartApiSelectShippingRateMock.mockResolvedValue(
					buildCart(
						[
							{ ...pickupRate, selected: false },
							{ ...deliveryRate, selected: true },
						],
						1000,
						'manual'
					)
				);

				await shippingAddressChangeHandler( event, elements );

				expect( cartApiSelectShippingRateMock ).toHaveBeenCalledWith( {
					package_id: 0,
					rate_id: 'flat_rate:14',
				} );
			} );

			it( 'reselects delivery when the origin field is absent (WC without origin tracking)', async () => {
				// buildCart without the third argument emits no selected_rate_origin —
				// the shape every WC release without the core origin-tracking fix sends.
				cartApiUpdateCustomerMock.mockResolvedValue(
					buildCart( [ pickupRate, deliveryRate ], 0 )
				);
				cartApiSelectShippingRateMock.mockResolvedValue(
					buildCart(
						[
							{ ...pickupRate, selected: false },
							{ ...deliveryRate, selected: true },
						],
						1000
					)
				);

				await shippingAddressChangeHandler( event, elements );

				expect( cartApiSelectShippingRateMock ).toHaveBeenCalledWith( {
					package_id: 0,
					rate_id: 'flat_rate:14',
				} );
			} );

			it( 'reselects delivery when the origin is explicitly null (no prior selection recorded)', async () => {
				cartApiUpdateCustomerMock.mockResolvedValue(
					buildCart( [ pickupRate, deliveryRate ], 0, null )
				);
				cartApiSelectShippingRateMock.mockResolvedValue(
					buildCart(
						[
							{ ...pickupRate, selected: false },
							{ ...deliveryRate, selected: true },
						],
						1000,
						'manual'
					)
				);

				await shippingAddressChangeHandler( event, elements );

				expect( cartApiSelectShippingRateMock ).toHaveBeenCalledWith( {
					package_id: 0,
					rate_id: 'flat_rate:14',
				} );
			} );

			it( 'resolves rates and package id through the express-checkout filters (deferred-shipping carts)', async () => {
				// Emulate the wc-subscriptions compatibility layer: for trial
				// subscriptions with deferred shipping the main package has no
				// rates and the effective rates/package id come from filters.
				addFilter(
					'wcpay.express-checkout.shipping-rates',
					'wcpay-test/deferred-shipping',
					( rates, cartData ) =>
						rates.length > 0 ? rates : cartData.extensions.testRates
				);
				addFilter(
					'wcpay.express-checkout.shipping-package-id',
					'wcpay-test/deferred-shipping',
					() => 3
				);

				try {
					const deferredCart = buildCart( [], 0 );
					deferredCart.extensions = {
						testRates: [ pickupRate, deliveryRate ],
					};
					cartApiUpdateCustomerMock.mockResolvedValue( deferredCart );
					cartApiSelectShippingRateMock.mockResolvedValue(
						buildCart(
							[
								{ ...pickupRate, selected: false },
								{ ...deliveryRate, selected: true },
							],
							1000
						)
					);

					await shippingAddressChangeHandler( event, elements );

					expect(
						cartApiSelectShippingRateMock
					).toHaveBeenCalledWith( {
						package_id: 3,
						rate_id: 'flat_rate:14',
					} );
					expect( event.reject ).not.toHaveBeenCalled();
					expect( event.resolve ).toHaveBeenCalled();
				} finally {
					removeFilter(
						'wcpay.express-checkout.shipping-rates',
						'wcpay-test/deferred-shipping'
					);
					removeFilter(
						'wcpay.express-checkout.shipping-package-id',
						'wcpay-test/deferred-shipping'
					);
				}
			} );
		} );
	} );

	describe( 'shippingRateChangeHandler', () => {
		let event;
		let elements;

		beforeEach( () => {
			event = {
				shippingRate: { id: 'flat_rate:14' },
				resolve: jest.fn(),
				reject: jest.fn(),
			};
			elements = {
				update: jest.fn( () => Promise.resolve() ),
			};
		} );

		afterEach( () => {
			jest.clearAllMocks();
			__resetElementCurrencyForTests();
		} );

		it( 'rejects the rate change and surfaces a message when the cart currency drifts from the element currency', async () => {
			rememberElementCurrency( 'usd' );
			const showError = jest.fn();

			cartApiSelectShippingRateMock.mockResolvedValue( {
				items: [],
				totals: {
					total_price: 1000,
					currency_minor_unit: 2,
					currency_code: 'EUR',
				},
			} );

			await shippingRateChangeHandler( event, elements, null, showError );

			expect( event.reject ).toHaveBeenCalled();
			expect( event.resolve ).not.toHaveBeenCalled();
			expect( elements.update ).not.toHaveBeenCalled();
			expect( showError ).toHaveBeenCalledWith(
				expect.stringContaining( 'EUR' )
			);
		} );
	} );

	describe( 'onConfirmHandler', () => {
		let api;
		let stripe;
		let elements;
		let completePayment;
		let abortPayment;
		let event;

		beforeEach( () => {
			api = {
				expressCheckoutECECreateOrder: jest.fn(),
				expressCheckoutECEPayForOrder: jest.fn(),
				confirmIntent: jest.fn(),
			};
			stripe = {
				createConfirmationToken: jest.fn().mockResolvedValue( {
					confirmationToken: { id: 'ctoken_123' },
				} ),
				createPaymentMethod: jest.fn().mockResolvedValue( {
					paymentMethod: { id: 'pm_123' },
				} ),
			};
			elements = {
				submit: jest.fn().mockResolvedValue( {} ),
			};
			completePayment = jest.fn();
			abortPayment = jest.fn();
			event = {
				billingDetails: {
					name: 'Card Holder Name',
					email: 'john.doe@example.com',
					address: {
						organization: 'Some Company',
						country: 'US',
						line1: '123 Main St',
						line2: 'Apt 4B',
						city: 'New York',
						state: 'NY',
						postal_code: '10001',
					},
					phone: '(123) 456-7890',
				},
				shippingAddress: {
					name: 'Card Holder Name',
					organization: 'Some Company',
					address: {
						country: 'US',
						line1: '123 Main St',
						line2: 'Apt 4B',
						city: 'New York',
						state: 'NY',
						postal_code: '10001',
					},
				},
				shippingRate: { id: 'rate_1' },
				expressPaymentType: 'express',
			};
		} );

		it( 'should abort payment if elements.submit fails', async () => {
			elements.submit.mockResolvedValue( {
				error: { message: 'Submit error' },
			} );

			await onConfirmHandler(
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event
			);

			expect( completePayment ).not.toHaveBeenCalled();
			expect( stripe.createConfirmationToken ).not.toHaveBeenCalled();
			expect( stripe.createPaymentMethod ).not.toHaveBeenCalled();
			expect( abortPayment ).toHaveBeenCalledWith( 'Submit error' );
		} );

		describe( 'using legacy payment methods', () => {
			beforeEach( () => {
				global.wcpayExpressCheckoutParams.flags.isEceUsingConfirmationTokens = false;
			} );

			it( 'should call stripe.createPaymentMethod', async () => {
				cartApiPlaceOrderMock.mockResolvedValue( {
					payment_result: {
						payment_status: 'success',
						redirect_url: 'https://example.com/success',
					},
				} );
				api.confirmIntent.mockReturnValue( true );

				await onConfirmHandler(
					api,
					stripe,
					elements,
					completePayment,
					abortPayment,
					event
				);

				expect( stripe.createPaymentMethod ).toHaveBeenCalledWith( {
					elements,
				} );
				expect( stripe.createConfirmationToken ).not.toHaveBeenCalled();
				expect( cartApiPlaceOrderMock ).toHaveBeenCalledWith(
					expect.objectContaining( {
						payment_data: expect.arrayContaining( [
							expect.objectContaining( {
								key: 'wcpay-payment-method',
								value: 'pm_123',
							} ),
						] ),
					} )
				);
			} );

			it( 'should abort payment if stripe.createPaymentMethod fails', async () => {
				stripe.createPaymentMethod.mockResolvedValue( {
					error: { message: 'Payment method error' },
				} );

				await onConfirmHandler(
					api,
					stripe,
					elements,
					completePayment,
					abortPayment,
					event
				);

				expect( completePayment ).not.toHaveBeenCalled();
				expect( stripe.createPaymentMethod ).toHaveBeenCalledWith( {
					elements,
				} );
				expect( stripe.createConfirmationToken ).not.toHaveBeenCalled();
				expect( abortPayment ).toHaveBeenCalledWith(
					'Payment method error'
				);
			} );
		} );

		describe( 'using confirmation tokens', () => {
			beforeEach( () => {
				global.wcpayExpressCheckoutParams.flags.isEceUsingConfirmationTokens = true;
			} );

			it( 'should call stripe.createConfirmationToken', async () => {
				cartApiPlaceOrderMock.mockResolvedValue( {
					payment_result: {
						payment_status: 'success',
						redirect_url: 'https://example.com/success',
					},
				} );
				api.confirmIntent.mockReturnValue( true );

				await onConfirmHandler(
					api,
					stripe,
					elements,
					completePayment,
					abortPayment,
					event
				);

				expect( stripe.createConfirmationToken ).toHaveBeenCalledWith( {
					elements,
				} );
				expect( stripe.createPaymentMethod ).not.toHaveBeenCalled();
				expect( cartApiPlaceOrderMock ).toHaveBeenCalledWith(
					expect.objectContaining( {
						payment_data: expect.arrayContaining( [
							expect.objectContaining( {
								key: 'wcpay-confirmation-token',
								value: 'ctoken_123',
							} ),
						] ),
					} )
				);
			} );

			it( 'should abort payment if stripe.createConfirmationToken fails', async () => {
				stripe.createConfirmationToken.mockResolvedValue( {
					error: { message: 'Confirmation token error' },
				} );

				await onConfirmHandler(
					api,
					stripe,
					elements,
					completePayment,
					abortPayment,
					event
				);

				expect( completePayment ).not.toHaveBeenCalled();
				expect( stripe.createConfirmationToken ).toHaveBeenCalledWith( {
					elements,
				} );
				expect( stripe.createPaymentMethod ).not.toHaveBeenCalled();
				expect( abortPayment ).toHaveBeenCalledWith(
					'Confirmation token error'
				);
			} );
		} );

		describe( 'paymentMethodTypes parameter', () => {
			beforeEach( () => {
				global.wcpayExpressCheckoutParams.flags.isEceUsingConfirmationTokens = true;
			} );

			it( 'should include paymentMethodTypes in payment_data for card payments', async () => {
				// enabled_methods is set to ['payment_request'] in global beforeEach
				cartApiPlaceOrderMock.mockResolvedValue( {
					payment_result: {
						payment_status: 'success',
						redirect_url: 'https://example.com/success',
					},
				} );
				api.confirmIntent.mockReturnValue( true );

				const paymentMethodTypes = [ 'card' ];

				await onConfirmHandler(
					api,
					stripe,
					elements,
					completePayment,
					abortPayment,
					event,
					paymentMethodTypes
				);

				expect( cartApiPlaceOrderMock ).toHaveBeenCalledWith(
					expect.objectContaining( {
						payment_data: expect.arrayContaining( [
							expect.objectContaining( {
								key: 'wcpay-express-payment-method-types',
								value: JSON.stringify( [ 'card' ] ),
							} ),
						] ),
					} )
				);
			} );

			it( 'should include paymentMethodTypes in payment_data for Amazon Pay', async () => {
				global.wcpayExpressCheckoutParams.enabled_methods = [
					'payment_request',
					'amazon_pay',
				];
				cartApiPlaceOrderMock.mockResolvedValue( {
					payment_result: {
						payment_status: 'success',
						redirect_url: 'https://example.com/success',
					},
				} );
				api.confirmIntent.mockReturnValue( true );

				const amazonPayEvent = {
					...event,
					expressPaymentType: 'amazon_pay',
				};
				const paymentMethodTypes = [ 'card', 'amazon_pay' ];

				await onConfirmHandler(
					api,
					stripe,
					elements,
					completePayment,
					abortPayment,
					amazonPayEvent,
					paymentMethodTypes
				);

				expect( cartApiPlaceOrderMock ).toHaveBeenCalledWith(
					expect.objectContaining( {
						payment_data: expect.arrayContaining( [
							expect.objectContaining( {
								key: 'wcpay-express-payment-method-types',
								value: JSON.stringify( [
									'card',
									'amazon_pay',
								] ),
							} ),
							expect.objectContaining( {
								key: 'express_payment_type',
								value: 'amazon_pay',
							} ),
						] ),
					} )
				);
			} );

			it( 'should include only amazon_pay in paymentMethodTypes when payment_request is disabled', async () => {
				global.wcpayExpressCheckoutParams.enabled_methods = [
					'amazon_pay',
				];
				cartApiPlaceOrderMock.mockResolvedValue( {
					payment_result: {
						payment_status: 'success',
						redirect_url: 'https://example.com/success',
					},
				} );
				api.confirmIntent.mockReturnValue( true );

				const amazonPayEvent = {
					...event,
					expressPaymentType: 'amazon_pay',
				};
				const paymentMethodTypes = [ 'amazon_pay' ];

				await onConfirmHandler(
					api,
					stripe,
					elements,
					completePayment,
					abortPayment,
					amazonPayEvent,
					paymentMethodTypes
				);

				expect( cartApiPlaceOrderMock ).toHaveBeenCalledWith(
					expect.objectContaining( {
						payment_data: expect.arrayContaining( [
							expect.objectContaining( {
								key: 'wcpay-express-payment-method-types',
								value: JSON.stringify( [ 'amazon_pay' ] ),
							} ),
							expect.objectContaining( {
								key: 'express_payment_type',
								value: 'amazon_pay',
							} ),
						] ),
					} )
				);
			} );
		} );

		it( 'should abort payment if cartApi.placeOrder throws an exception because of a network error', async () => {
			cartApiPlaceOrderMock.mockRejectedValue( {
				code: 'fetch_error',
				message: 'You are probably offline.',
			} );

			await onConfirmHandler(
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event
			);

			expect( cartApiPlaceOrderMock ).toHaveBeenCalled();
			expect( abortPayment ).toHaveBeenCalledWith(
				'You are probably offline.'
			);
			expect( completePayment ).not.toHaveBeenCalled();
		} );

		it( 'should abort payment if cartApi.placeOrder throws an exception from the store', async () => {
			cartApiPlaceOrderMock.mockRejectedValue( {
				status: 400,
				json: () =>
					Promise.resolve( {
						order_id: 1111111,
						status: 'failed',
						order_key: 'wc_order_f41lur3',
						order_number: '1111111',
						customer_note: '',
						customer_id: 1,
						billing_address: {
							first_name: 'Card',
							last_name: 'Holder Name',
							country: 'US',
							address_1: '123 Main St',
							address_2: 'Apt 4B',
							city: 'New York',
							state: 'NY',
							postcode: '10001',
							company: 'Some Company',
							email: 'john.doe@example.com',
							phone: '1234567890',
						},
						shipping_address: {
							first_name: 'Card',
							last_name: 'Holder Name',
							country: 'US',
							address_1: '123 Main St',
							address_2: 'Apt 4B',
							city: 'New York',
							state: 'NY',
							postcode: '10001',
							company: 'Some Company',
							phone: '1234567890',
						},
						payment_method: 'woocommerce_payments',
						payment_result: {
							payment_status: 'failure',
							payment_details: [
								{
									key: 'errorMessage',
									value: 'Error: Your card was declined.',
								},
								{
									key: 'result',
									value: 'fail',
								},
								{
									key: 'redirect',
									value: '',
								},
							],
							redirect_url: '',
						},
						additional_fields: [],
						extensions: {},
					} ),
			} );

			await onConfirmHandler(
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event
			);

			expect( cartApiPlaceOrderMock ).toHaveBeenCalled();
			expect( abortPayment ).toHaveBeenCalledWith(
				'Error: Your card was declined.'
			);
			expect( completePayment ).not.toHaveBeenCalled();
		} );

		it( 'should extract redirect URL from payment_details when redirect_url is empty for 3DS authentication', async () => {
			const threeDSRedirectUrl =
				'#wcpay-confirm-pi:123:pi_1234567890abcdef_secret_test1234567890abcdef:fake_nonce';

			cartApiPlaceOrderMock.mockResolvedValue( {
				order_id: 123,
				status: 'pending',
				order_key: 'wc_order_test123',
				order_number: '123',
				customer_note: '',
				customer_id: 1,
				billing_address: {
					first_name: 'Card',
					last_name: 'Holder Name',
					country: 'US',
					address_1: '123 Main St',
					address_2: 'Apt 4B',
					city: 'New York',
					state: 'NY',
					postcode: '10001',
					company: 'Some Company',
					email: 'john.doe@example.com',
					phone: '1234567890',
				},
				shipping_address: {
					first_name: 'Card',
					last_name: 'Holder Name',
					country: 'US',
					address_1: '123 Main St',
					address_2: 'Apt 4B',
					city: 'New York',
					state: 'NY',
					postcode: '10001',
					company: 'Some Company',
					phone: '1234567890',
				},
				payment_method: 'woocommerce_payments',
				payment_result: {
					payment_status: 'success',
					payment_details: [
						{
							key: 'result',
							value: 'success',
						},
						{
							key: 'redirect',
							value: threeDSRedirectUrl,
						},
						{
							key: 'payment_method',
							value: 'pm_test1234567890abcdef',
						},
					],
					redirect_url: '', // Empty redirect_url - this is the bug scenario
				},
				additional_fields: [],
				extensions: {},
			} );

			api.confirmIntent.mockResolvedValue( true );

			await onConfirmHandler(
				api,
				stripe,
				elements,
				completePayment,
				abortPayment,
				event
			);

			expect( cartApiPlaceOrderMock ).toHaveBeenCalled();
			expect( api.confirmIntent ).toHaveBeenCalledWith(
				threeDSRedirectUrl
			);
			expect( completePayment ).toHaveBeenCalled();
			expect( abortPayment ).not.toHaveBeenCalled();
		} );
	} );
} );
