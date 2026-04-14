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

let mockCartData = {};
jest.mock( '@wordpress/data', () => ( {
	select: () => ( {
		getCartData: () => mockCartData,
	} ),
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

const buildCartData = ( {
	items = [],
	totals = {},
	extensions = {},
} = {} ) => ( {
	items,
	totals: {
		total_items: '0',
		total_items_tax: '0',
		total_fees: '0',
		total_fees_tax: '0',
		total_discount: '0',
		total_discount_tax: '0',
		total_shipping: '0',
		total_shipping_tax: '0',
		total_price: '0',
		total_tax: '0',
		currency_minor_unit: 2,
		...totals,
	},
	extensions,
} );

const buildCartItem = ( {
	name = 'Test Product',
	quantity = 1,
	lineSubtotal = '0',
	lineSubtotalTax = '0',
	currencyMinorUnit = 2,
} = {} ) => ( {
	name,
	quantity,
	variation: [],
	item_data: [],
	totals: {
		line_subtotal: lineSubtotal,
		line_subtotal_tax: lineSubtotalTax,
		currency_minor_unit: currencyMinorUnit,
	},
	prices: {
		price: lineSubtotal,
		currency_minor_unit: currencyMinorUnit,
	},
} );

describe( 'useExpressCheckout', () => {
	beforeEach( () => {
		global.$ = jQueryMock;
		global.jQuery = jQueryMock;
		window.wcpayExpressCheckoutParams.checkout = {
			currency_decimals: 2,
		};
		mockCartData = buildCartData();
	} );

	it( 'should provide product-level line items from cart data', () => {
		const onClickMock = jest.fn();
		const event = { resolve: jest.fn() };

		mockCartData = buildCartData( {
			items: [
				buildCartItem( {
					name: 'Beanie with Logo',
					lineSubtotal: '4000',
					lineSubtotalTax: '330',
				} ),
			],
			totals: {
				total_items: '4000',
				total_items_tax: '330',
				total_price: '4330',
				total_tax: '330',
			},
		} );

		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [],
					cartTotal: { label: 'Total', value: 4330 },
					currency: { minorUnit: 2 },
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
					{ amount: 4000, name: 'Beanie with Logo' },
					{ amount: 330, name: 'Tax' },
				],
			} )
		);
		expect( onClickMock ).toHaveBeenCalled();
	} );

	it( 'should not provide line items if the totals have rounding errors', () => {
		const onClickMock = jest.fn();
		const event = { resolve: jest.fn() };

		mockCartData = buildCartData( {
			items: [
				buildCartItem( {
					name: 'Product A',
					lineSubtotal: '1000',
					lineSubtotalTax: '100',
				} ),
			],
			totals: {
				total_items: '1000',
				total_items_tax: '100',
				total_tax: '100',
				// Cart total is less than sum of line items (rounding error scenario).
				total_price: '1050',
			},
		} );

		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [],
					cartTotal: { label: 'Total', value: 1050 },
					currency: { minorUnit: 2 },
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

	it( 'should transform shipping rate amounts correctly with standard 2-decimal currency', () => {
		const event = { resolve: jest.fn() };
		window.wcpayExpressCheckoutParams.checkout.currency_decimals = 2;

		mockCartData = buildCartData( {
			items: [
				buildCartItem( {
					name: 'Test Product',
					lineSubtotal: '1000',
					lineSubtotalTax: '0',
				} ),
			],
			totals: {
				total_items: '1000',
				total_price: '1500',
				total_shipping: '500',
			},
		} );

		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [],
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

	it( 'should transform shipping rate amounts correctly with zero-decimal currency (JPY, KRW)', () => {
		const event = { resolve: jest.fn() };
		window.wcpayExpressCheckoutParams.checkout.currency_decimals = 0;

		mockCartData = buildCartData( {
			items: [
				buildCartItem( {
					name: 'Test Product',
					lineSubtotal: '10',
					currencyMinorUnit: 0,
				} ),
			],
			totals: {
				total_items: '10',
				total_price: '15',
				total_shipping: '5',
				currency_minor_unit: 0,
			},
		} );

		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [],
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

	it( 'should transform shipping rate amounts correctly with USD configured to display zero decimals', () => {
		const event = { resolve: jest.fn() };
		// Mocking USD configured to display with 0 decimals - Stripe still needs cents.
		window.wcpayExpressCheckoutParams.checkout.currency_decimals = 2;

		mockCartData = buildCartData( {
			items: [
				buildCartItem( {
					name: 'Test Product',
					lineSubtotal: '10',
					currencyMinorUnit: 0,
				} ),
			],
			totals: {
				total_items: '10',
				total_price: '15',
				total_shipping: '5',
				currency_minor_unit: 0,
			},
		} );

		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [],
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

	it( 'should exclude line items when total is less than sum of display items', () => {
		const event = { resolve: jest.fn() };
		window.wcpayExpressCheckoutParams.checkout.currency_decimals = 2;

		mockCartData = buildCartData( {
			items: [
				buildCartItem( {
					name: 'Product A',
					lineSubtotal: '1000',
					lineSubtotalTax: '100',
				} ),
			],
			totals: {
				total_items: '1000',
				total_tax: '100',
				// Cart total is less than sum of display items (rounding error).
				total_price: '1050',
			},
		} );

		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [],
					cartTotal: {
						label: 'Total',
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
