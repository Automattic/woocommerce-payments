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
	} );

	it( 'should provide no shipping rates when not required on click', () => {
		const onClickMock = jest.fn();
		const event = { resolve: jest.fn() };
		const { result } = renderHook( () =>
			useExpressCheckout( {
				billing: {
					cartTotalItems: [],
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
} );
