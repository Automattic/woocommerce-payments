/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import PaymentRequestCartApi from '../cart-api';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

describe( 'PaymentRequestCartApi', () => {
	afterEach( () => {
		jest.resetAllMocks();
	} );

	it( 'should allow to create an anonymous cart for a specific class instance', async () => {
		const headers = new Headers();
		headers.append( 'Nonce', 'nonce-value' );
		headers.append( 'Cart-Token', 'cart-token-value' );
		apiFetch.mockResolvedValue( {
			headers: headers,
		} );

		const api = new PaymentRequestCartApi();
		const anotherApi = new PaymentRequestCartApi();

		await api.createAnonymousCart();

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'GET',
				path: '/wc/store/v1/cart',
				credentials: 'omit',
				parse: false,
			} )
		);

		apiFetch.mockClear();
		apiFetch.mockResolvedValue( {} );

		await api.updateCustomer( {
			billing_address: { first_name: 'First' },
		} );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'POST',
				path: '/wc/store/v1/cart/update-customer',
				credentials: 'omit',
				headers: expect.objectContaining( {
					'X-WooPayments-Express-Payment-Request': true,
					Nonce: 'nonce-value',
					'Cart-Token': 'cart-token-value',
				} ),
				data: expect.objectContaining( {
					billing_address: { first_name: 'First' },
				} ),
			} )
		);

		apiFetch.mockClear();
		await anotherApi.updateCustomer( {
			billing_address: { last_name: 'Last' },
		} );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'POST',
				path: '/wc/store/v1/cart/update-customer',
				credentials: 'omit',
				// in this case, no additional headers should have been submitted.
				headers: expect.objectContaining( {
					'X-WooPayments-Express-Payment-Request': true,
				} ),
				data: expect.objectContaining( {
					billing_address: { last_name: 'Last' },
				} ),
			} )
		);
	} );
} );
