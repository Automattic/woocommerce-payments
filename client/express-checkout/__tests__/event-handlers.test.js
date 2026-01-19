/**
 * Internal dependencies
 */
import {
	shippingAddressChangeHandler,
	onConfirmHandler,
	setCartApiHandler,
} from '../event-handlers';

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
				update: jest.fn(),
			};
		} );

		afterEach( () => {
			jest.clearAllMocks();
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

			expect( elements.update ).toHaveBeenCalledWith( { amount: 1000 } );
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

			expect( elements.update ).toHaveBeenCalledWith( { amount: 1000 } );
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
			expect( abortPayment ).toHaveBeenCalledWith( 'Submit error' );
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
			expect( abortPayment ).toHaveBeenCalledWith(
				'Confirmation token error'
			);
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
