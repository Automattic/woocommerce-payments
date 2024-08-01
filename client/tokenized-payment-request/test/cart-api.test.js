/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import PaymentRequestCartApi from '../cart-api';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

global.wcpayPaymentRequestParams = {};
global.wcpayPaymentRequestParams.nonce = {};
global.wcpayPaymentRequestParams.nonce.store_api_nonce =
	'global_store_api_nonce';
global.wcpayPaymentRequestParams.nonce.tokenized_cart_nonce =
	'global_tokenized_cart_nonce';
global.wcpayPaymentRequestParams.nonce.tokenized_cart_session_nonce =
	'global_tokenized_cart_session_nonce';
global.wcpayPaymentRequestParams.checkout = {};
global.wcpayPaymentRequestParams.checkout.currency_code = 'USD';

describe( 'PaymentRequestCartApi', () => {
	afterEach( () => {
		jest.resetAllMocks();
	} );

	it( 'should allow to create an anonymous cart for a specific class instance, without affecting other instances', async () => {
		global.wcpayPaymentRequestParams.button_context = 'product';
		const headers = new Headers();
		headers.append(
			'X-WooPayments-Tokenized-Cart-Session',
			'tokenized_cart_session'
		);
		headers.append( 'Nonce', 'nonce-value' );
		apiFetch.mockResolvedValue( {
			headers: headers,
			json: () => Promise.resolve( {} ),
		} );

		const api = new PaymentRequestCartApi();
		const anotherApi = new PaymentRequestCartApi();

		api.useSeparateCart();
		await api.getCart();

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'GET',
				path: expect.stringContaining( '/wc/store/v1/cart' ),
				headers: expect.objectContaining( {
					'X-WooPayments-Tokenized-Cart-Session': '',
					'X-WooPayments-Tokenized-Cart-Session-Nonce':
						'global_tokenized_cart_session_nonce',
					'X-WooPayments-Tokenized-Cart-Nonce':
						'global_tokenized_cart_nonce',
					Nonce: 'global_store_api_nonce',
				} ),
			} )
		);

		apiFetch.mockClear();
		apiFetch.mockResolvedValue( {
			headers: new Headers(),
			json: () => Promise.resolve( {} ),
		} );

		await api.updateCustomer( {
			billing_address: { first_name: 'First' },
		} );
		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				method: 'POST',
				path: expect.stringContaining(
					'/wc/store/v1/cart/update-customer'
				),
				headers: expect.objectContaining( {
					'X-WooPayments-Tokenized-Cart': true,
					'X-WooPayments-Tokenized-Cart-Session-Nonce':
						'global_tokenized_cart_session_nonce',
					'X-WooPayments-Tokenized-Cart-Nonce':
						'global_tokenized_cart_nonce',
					'X-WooPayments-Tokenized-Cart-Session':
						'tokenized_cart_session',
					Nonce: 'nonce-value',
				} ),
				data: expect.objectContaining( {
					billing_address: { first_name: 'First' },
				} ),
			} )
		);

		apiFetch.mockClear();
		apiFetch.mockResolvedValue( {
			headers: new Headers(),
			json: () => Promise.resolve( {} ),
		} );
		await anotherApi.updateCustomer( {
			billing_address: { last_name: 'Last' },
		} );
		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				method: 'POST',
				path: expect.stringContaining(
					'/wc/store/v1/cart/update-customer'
				),
				// in this case, no additional headers should have been submitted.
				headers: expect.objectContaining( {
					'X-WooPayments-Tokenized-Cart': true,
					'X-WooPayments-Tokenized-Cart-Nonce':
						'global_tokenized_cart_nonce',
					Nonce: 'global_store_api_nonce',
				} ),
				data: expect.objectContaining( {
					billing_address: { last_name: 'Last' },
				} ),
			} )
		);
	} );

	it( 'should call `/cart/update-customer` with the global headers if the cart is not anonymous', async () => {
		global.wcpayPaymentRequestParams.button_context = 'cart';
		apiFetch.mockResolvedValue( {
			headers: new Headers(),
			json: () => Promise.resolve( {} ),
		} );
		const api = new PaymentRequestCartApi();

		await api.updateCustomer( {
			billing_address: { last_name: 'Last' },
		} );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'POST',
				path: expect.stringContaining(
					'/wc/store/v1/cart/update-customer'
				),
				// in this case, no additional headers should have been submitted.
				headers: expect.objectContaining( {
					'X-WooPayments-Tokenized-Cart': true,
					'X-WooPayments-Tokenized-Cart-Nonce':
						'global_tokenized_cart_nonce',
				} ),
				data: expect.objectContaining( {
					billing_address: { last_name: 'Last' },
				} ),
			} )
		);
	} );

	it( 'should store received header information for subsequent usage', async () => {
		global.wcpayPaymentRequestParams.button_context = 'cart';
		const headers = new Headers();
		headers.append( 'Nonce', 'nonce-value' );
		apiFetch.mockResolvedValue( {
			headers,
			json: () => Promise.resolve( {} ),
		} );
		const api = new PaymentRequestCartApi();

		await api.getCart();

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'GET',
				path: expect.stringContaining( '/wc/store/v1/cart' ),
				headers: expect.objectContaining( {
					'X-WooPayments-Tokenized-Cart-Session-Nonce': undefined,
					'X-WooPayments-Tokenized-Cart-Nonce':
						'global_tokenized_cart_nonce',
				} ),
			} )
		);

		await api.updateCustomer( {
			billing_address: { last_name: 'Last' },
		} );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'POST',
				path: expect.stringContaining(
					'/wc/store/v1/cart/update-customer'
				),
				// in this case, no additional headers should have been submitted.
				headers: expect.objectContaining( {
					'X-WooPayments-Tokenized-Cart-Session-Nonce': undefined,
					'X-WooPayments-Tokenized-Cart': true,
					'X-WooPayments-Tokenized-Cart-Nonce':
						'global_tokenized_cart_nonce',
					Nonce: 'nonce-value',
				} ),
			} )
		);
	} );
} );
