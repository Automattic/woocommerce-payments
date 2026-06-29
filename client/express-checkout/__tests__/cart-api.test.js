/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import ExpressCheckoutCartApi from '../cart-api';
import {
	rememberElementCurrency,
	__resetElementCurrencyForTests,
} from '../utils/element-currency-cache';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

global.wcpayExpressCheckoutParams = {};
global.wcpayExpressCheckoutParams.nonce = {};
global.wcpayExpressCheckoutParams.nonce.store_api_nonce =
	'global_store_api_nonce';
global.wcpayExpressCheckoutParams.nonce.tokenized_cart_nonce =
	'global_tokenized_cart_nonce';
global.wcpayExpressCheckoutParams.nonce.tokenized_cart_session_nonce =
	'global_tokenized_cart_session_nonce';
global.wcpayExpressCheckoutParams.checkout = {};
global.wcpayExpressCheckoutParams.checkout.currency_code = 'USD';

describe( 'ExpressCheckoutCartApi', () => {
	afterEach( () => {
		jest.resetAllMocks();
		__resetElementCurrencyForTests();
	} );

	it( 'should allow to create an anonymous cart for a specific class instance, without affecting other instances', async () => {
		global.wcpayExpressCheckoutParams.button_context = 'product';
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

		const api = new ExpressCheckoutCartApi();
		const anotherApi = new ExpressCheckoutCartApi();

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
		global.wcpayExpressCheckoutParams.button_context = 'cart';
		apiFetch.mockResolvedValue( {
			headers: new Headers(),
			json: () => Promise.resolve( {} ),
		} );
		const api = new ExpressCheckoutCartApi();

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
		global.wcpayExpressCheckoutParams.button_context = 'cart';
		const headers = new Headers();
		headers.append( 'Nonce', 'nonce-value' );
		apiFetch.mockResolvedValue( {
			headers,
			json: () => Promise.resolve( {} ),
		} );
		const api = new ExpressCheckoutCartApi();

		await api.getCart();

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'GET',
				path: expect.stringContaining( '/wc/store/v1/cart' ),
				headers: expect.objectContaining( {
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
					'X-WooPayments-Tokenized-Cart': true,
					'X-WooPayments-Tokenized-Cart-Nonce':
						'global_tokenized_cart_nonce',
					Nonce: 'nonce-value',
				} ),
			} )
		);
	} );

	// Regression test for WOOPMNT-6135.
	it( 'should keep sending the page-localized store_api_nonce when responses lack a rotating Nonce header', async () => {
		// On shortcode checkout the custom session handler does not engage,
		// and responses may not carry a rotating Nonce header. Reading
		// `response.headers.get( 'Nonce' )` returns `null` in that case, and
		// we must not let that overwrite the working store_api_nonce default
		// on the next request — otherwise it serializes to the literal string
		// "null" and the Store API rejects with `woocommerce_rest_missing_nonce`.
		global.wcpayExpressCheckoutParams.button_context = 'cart';
		apiFetch.mockResolvedValue( {
			headers: new Headers(),
			json: () => Promise.resolve( {} ),
		} );
		const api = new ExpressCheckoutCartApi();

		await api.getCart();
		await api.updateCustomer( {
			billing_address: { last_name: 'Last' },
		} );

		const updateCustomerCall = apiFetch.mock.calls[ 1 ][ 0 ];
		expect( updateCustomerCall.headers.Nonce ).toBe(
			'global_store_api_nonce'
		);
	} );

	// Regression test for WOOPMNT-6135.
	it( 'should omit the session-nonce header on non-product contexts so it never serializes as "undefined"', async () => {
		global.wcpayExpressCheckoutParams.button_context = 'cart';
		apiFetch.mockResolvedValue( {
			headers: new Headers(),
			json: () => Promise.resolve( {} ),
		} );
		const api = new ExpressCheckoutCartApi();

		await api.getCart();

		const callArgs = apiFetch.mock.calls[ 0 ][ 0 ];
		expect( callArgs.headers ).not.toHaveProperty(
			'X-WooPayments-Tokenized-Cart-Session-Nonce'
		);
	} );

	it( 'placeOrder forwards the cached element currency on the X-WooPayments-Payment-Currency header', async () => {
		global.wcpayExpressCheckoutParams.button_context = 'cart';
		apiFetch.mockResolvedValue( {
			headers: new Headers(),
			json: () => Promise.resolve( {} ),
		} );
		rememberElementCurrency( 'eur' );

		const api = new ExpressCheckoutCartApi();
		await api.placeOrder( { payment_method: 'woocommerce_payments' } );

		const callArgs = apiFetch.mock.calls[ 0 ][ 0 ];

		expect( callArgs.headers[ 'X-WooPayments-Payment-Currency' ] ).toBe(
			'eur'
		);
	} );

	it( 'placeOrder omits the X-WooPayments-Payment-Currency header when no element currency was cached', async () => {
		global.wcpayExpressCheckoutParams.button_context = 'cart';
		apiFetch.mockResolvedValue( {
			headers: new Headers(),
			json: () => Promise.resolve( {} ),
		} );

		const api = new ExpressCheckoutCartApi();
		await api.placeOrder( { payment_method: 'woocommerce_payments' } );

		const callArgs = apiFetch.mock.calls[ 0 ][ 0 ];

		expect( callArgs.headers ).not.toHaveProperty(
			'X-WooPayments-Payment-Currency'
		);
	} );

	it( 'should send the session-nonce header on product contexts', async () => {
		global.wcpayExpressCheckoutParams.button_context = 'product';
		apiFetch.mockResolvedValue( {
			headers: new Headers(),
			json: () => Promise.resolve( {} ),
		} );
		const api = new ExpressCheckoutCartApi();

		await api.getCart();

		const callArgs = apiFetch.mock.calls[ 0 ][ 0 ];
		expect(
			callArgs.headers[ 'X-WooPayments-Tokenized-Cart-Session-Nonce' ]
		).toBe( 'global_tokenized_cart_session_nonce' );
	} );
} );
