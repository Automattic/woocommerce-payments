/**
 * External dependencies
 */
import { renderHook } from '@testing-library/react-hooks';

/**
 * Internal dependencies
 */
import { useExpressCheckout } from '../use-express-checkout';

jest.mock( '@stripe/react-stripe-js', () => ( {
	useElements: jest.fn(),
	useStripe: jest.fn(),
} ) );
jest.mock( 'tracks', () => ( {
	recordUserEvent: jest.fn(),
} ) );

const jQueryMock = ( selector ) => {
	if ( typeof selector === 'function' ) {
		return selector( jQueryMock );
	}

	return {
		on: () => null,
		val: () => null,
		is: () => null,
		remove: () => null,
	};
};
jQueryMock.blockUI = () => null;

window.wcpayExpressCheckoutParams = {};
window.wcpayExpressCheckoutParams.checkout = {};

describe( 'useExpressCheckout', () => {
	beforeEach( () => {
		global.$ = jQueryMock;
		global.jQuery = jQueryMock;
		window.wcpayExpressCheckoutParams.checkout = {
			currency_decimals: 2,
		};
	} );

	it( 'should provide the line items', () => {
		const onClickMock = jest.fn();
		const event = { resolve: jest.fn() };
		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [
						{
							key: 'total_items',
							label: 'Subtotal:',
							value: 4000,
							valueWithTax: 4330,
						},
						{
							key: 'total_fees',
							label: 'Fees:',
							value: 0,
							valueWithTax: 0,
						},
						{
							key: 'total_discount',
							label: 'Discount:',
							value: 0,
							valueWithTax: 0,
						},
						{
							key: 'total_tax',
							label: 'Taxes:',
							value: 330,
							valueWithTax: 330,
						},
						{
							key: 'total_shipping',
							label: 'Shipping:',
							value: 0,
							valueWithTax: 0,
						},
					],
					cartTotal: {
						label: 'Total',
						value: 4330,
					},
					currency: {
						minorUnit: 2,
					},
				},
				shippingData: {
					needsShipping: false,
					shippingRates: [],
				},
				onClick: onClickMock,
				onClose: {},
				setExpressPaymentError: {},
			} )
		);

		expect( onClickMock ).not.toHaveBeenCalled();

		result.current.onButtonClick( event );

		expect( event.resolve ).toHaveBeenCalledWith(
			expect.objectContaining( {
				lineItems: [
					{ amount: 4000, name: 'Subtotal:' },
					{ amount: 0, name: 'Fees:' },
					{ amount: -0, name: 'Discount:' },
					{ amount: 330, name: 'Taxes:' },
					{ amount: 0, name: 'Shipping:' },
				],
			} )
		);
		expect( onClickMock ).toHaveBeenCalled();
	} );

	it( "should not provide the line items if the totals don't match", () => {
		const onClickMock = jest.fn();
		const event = { resolve: jest.fn() };
		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [
						{
							key: 'total_items',
							label: 'Subtotal:',
							value: 4000,
							valueWithTax: 4330,
						},
						{
							key: 'total_fees',
							label: 'Fees:',
							value: 0,
							valueWithTax: 0,
						},
						{
							key: 'total_discount',
							label: 'Discount:',
							value: 0,
							valueWithTax: 0,
						},
						{
							key: 'total_tax',
							label: 'Taxes:',
							value: 330,
							valueWithTax: 330,
						},
						{
							key: 'total_shipping',
							label: 'Shipping:',
							value: 0,
							valueWithTax: 0,
						},
					],
					cartTotal: {
						label: 'Total',
						// simulating a total amount that is lower than the sum of the values of `cartTotalItems`
						// this scenario happens with the Gift Cards plugin.
						value: 400,
					},
					currency: {
						minorUnit: 2,
					},
				},
				shippingData: {
					needsShipping: false,
					shippingRates: [],
				},
				onClick: onClickMock,
				onClose: {},
				setExpressPaymentError: {},
			} )
		);

		expect( onClickMock ).not.toHaveBeenCalled();

		result.current.onButtonClick( event );

		expect( event.resolve ).toHaveBeenCalledWith(
			expect.objectContaining( {
				lineItems: [],
			} )
		);
		expect( onClickMock ).toHaveBeenCalled();
	} );

	it( 'should provide no shipping rates when not required on click', () => {
		const onClickMock = jest.fn();
		const event = { resolve: jest.fn() };
		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [],
					cartTotal: {
						label: 'Total',
						value: 448,
					},
					currency: {
						minorUnit: 2,
					},
				},
				shippingData: {
					needsShipping: false,
					shippingRates: [],
				},
				onClick: onClickMock,
				onClose: {},
				setExpressPaymentError: {},
			} )
		);

		expect( onClickMock ).not.toHaveBeenCalled();

		result.current.onButtonClick( event );

		expect( event.resolve ).toHaveBeenCalledWith(
			expect.objectContaining( {
				shippingRates: undefined,
				shippingAddressRequired: false,
			} )
		);
		expect( onClickMock ).toHaveBeenCalled();
	} );

	it( 'should provide the shipping rates on click', () => {
		const event = { resolve: jest.fn() };
		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [],
					cartTotal: {
						label: 'Total',
						value: 448,
					},
					currency: {
						minorUnit: 2,
					},
				},
				shippingData: {
					needsShipping: true,
					shippingRates: [
						{
							shipping_rates: [
								{
									rate_id: '1',
									price: '10',
									name: 'Fake shipping rate',
								},
							],
						},
					],
				},
				onClick: jest.fn(),
				onClose: {},
				setExpressPaymentError: {},
			} )
		);

		result.current.onButtonClick( event );

		expect( event.resolve ).toHaveBeenCalledWith(
			expect.objectContaining( {
				shippingRates: expect.arrayContaining( [
					{
						id: '1',
						displayName: 'Fake shipping rate',
						amount: 10,
					},
				] ),
				shippingAddressRequired: true,
			} )
		);
	} );

	it( 'should provide the shipping rates with fallback on click', () => {
		const event = { resolve: jest.fn() };
		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [],
					cartTotal: {
						label: 'Total',
						value: 448,
					},
					currency: {
						minorUnit: 2,
					},
				},
				shippingData: {
					needsShipping: true,
					shippingRates: [],
				},
				onClick: jest.fn(),
				onClose: {},
				setExpressPaymentError: {},
			} )
		);

		result.current.onButtonClick( event );

		expect( event.resolve ).toHaveBeenCalledWith(
			expect.objectContaining( {
				shippingRates: expect.arrayContaining( [
					{
						id: 'pending',
						displayName: 'Pending',
						amount: 0,
					},
				] ),
				shippingAddressRequired: true,
			} )
		);
	} );

	it( 'should transform amounts correctly with standard 2-decimal currency (USD, EUR)', () => {
		const event = { resolve: jest.fn() };
		window.wcpayExpressCheckoutParams.checkout.currency_decimals = 2;

		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [
						{
							key: 'total_items',
							label: 'Subtotal:',
							value: 1000,
							valueWithTax: 1000,
						},
						{
							key: 'total_tax',
							label: 'Tax:',
							value: 100,
							valueWithTax: 100,
						},
					],
					cartTotal: {
						label: 'Total',
						value: 1600,
					},
					currency: {
						minorUnit: 2,
					},
				},
				shippingData: {
					needsShipping: true,
					shippingRates: [
						{
							shipping_rates: [
								{
									rate_id: 'flat_rate',
									price: '500',
									name: 'Flat Rate',
								},
							],
						},
					],
				},
				onClick: jest.fn(),
				onClose: {},
				setExpressPaymentError: {},
			} )
		);

		result.current.onButtonClick( event );

		expect( event.resolve ).toHaveBeenCalledWith(
			expect.objectContaining( {
				lineItems: [
					{ amount: 1000, name: 'Subtotal:' },
					{ amount: 100, name: 'Tax:' },
				],
				shippingRates: [
					{
						id: 'flat_rate',
						displayName: 'Flat Rate',
						amount: 500,
					},
				],
			} )
		);
	} );

	it( 'should transform amounts correctly with zero-decimal currency (JPY, KRW)', () => {
		const event = { resolve: jest.fn() };
		window.wcpayExpressCheckoutParams.checkout.currency_decimals = 0;

		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [
						{
							key: 'total_items',
							label: 'Subtotal:',
							value: 10,
							valueWithTax: 10,
						},
					],
					cartTotal: {
						label: 'Total',
						value: 15,
					},
					currency: {
						code: 'KRW',
						minorUnit: 0,
					},
				},
				shippingData: {
					needsShipping: true,
					shippingRates: [
						{
							shipping_rates: [
								{
									rate_id: 'flat_rate',
									price: '5',
									name: 'Flat Rate',
								},
							],
						},
					],
				},
				onClick: jest.fn(),
				onClose: {},
				setExpressPaymentError: {},
			} )
		);

		result.current.onButtonClick( event );

		expect( event.resolve ).toHaveBeenCalledWith(
			expect.objectContaining( {
				lineItems: [ { amount: 10, name: 'Subtotal:' } ],
				shippingRates: [
					{
						id: 'flat_rate',
						displayName: 'Flat Rate',
						amount: 5,
					},
				],
			} )
		);
	} );

	it( 'should transform amounts correctly with USD configured to display zero decimals', () => {
		const event = { resolve: jest.fn() };
		// mocking a configured to display USD with 0 decimals - ensuring that Stripe still receives decimals
		window.wcpayExpressCheckoutParams.checkout.currency_decimals = 2;

		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [
						{
							key: 'total_items',
							label: 'Subtotal:',
							value: 10,
							valueWithTax: 10,
						},
					],
					cartTotal: {
						label: 'Total',
						value: 15,
					},
					currency: {
						minorUnit: 0,
					},
				},
				shippingData: {
					needsShipping: true,
					shippingRates: [
						{
							shipping_rates: [
								{
									rate_id: 'flat_rate',
									price: '5',
									name: 'Flat Rate',
								},
							],
						},
					],
				},
				onClick: jest.fn(),
				onClose: {},
				setExpressPaymentError: {},
			} )
		);

		result.current.onButtonClick( event );

		expect( event.resolve ).toHaveBeenCalledWith(
			expect.objectContaining( {
				lineItems: [ { amount: 1000, name: 'Subtotal:' } ],
				shippingRates: [
					{
						id: 'flat_rate',
						displayName: 'Flat Rate',
						amount: 500,
					},
				],
			} )
		);
	} );

	it( 'should exclude line items when transformed cart total is less than transformed line items total', () => {
		const event = { resolve: jest.fn() };
		window.wcpayExpressCheckoutParams.checkout.currency_decimals = 2;

		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [
						{
							key: 'total_items',
							label: 'Subtotal:',
							value: 1000,
							valueWithTax: 1000,
						},
						{
							key: 'total_tax',
							label: 'Tax:',
							value: 100,
							valueWithTax: 100,
						},
					],
					cartTotal: {
						label: 'Total',
						// Cart total is less than sum of line items (rounding error scenario)
						value: 1050,
					},
					currency: {
						minorUnit: 2,
					},
				},
				shippingData: {
					needsShipping: false,
					shippingRates: [],
				},
				onClick: jest.fn(),
				onClose: {},
				setExpressPaymentError: {},
			} )
		);

		result.current.onButtonClick( event );

		expect( event.resolve ).toHaveBeenCalledWith(
			expect.objectContaining( {
				lineItems: [],
			} )
		);
	} );
} );
