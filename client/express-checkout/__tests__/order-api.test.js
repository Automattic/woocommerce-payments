/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import ExpressCheckoutOrderApi from '../order-api';

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

global.wcpayExpressCheckoutParams = {};
global.wcpayExpressCheckoutParams.nonce = {};
global.wcpayExpressCheckoutParams.nonce.store_api_nonce =
	'global_store_api_nonce';

describe( 'ExpressCheckoutOrderApi', () => {
	afterEach( () => {
		jest.resetAllMocks();
	} );

	it( 'gets order data with the provided arguments', async () => {
		const api = new ExpressCheckoutOrderApi( {
			orderId: '1',
			key: 'key_123',
			billingEmail: 'cheese@toast.com',
		} );

		await api.getCart();
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'GET',
				path: expect.stringMatching(
					// I am using a regex to ensure the order of the parameters doesn't matter.
					/(?=.*\/wc\/store\/v1\/order\/1)(?=.*billing_email=cheese%40toast.com)(?=.*key=key_123)/
				),
			} )
		);
	} );

	it( 'places an order', async () => {
		const api = new ExpressCheckoutOrderApi( {
			orderId: '1',
			key: 'key_123',
			billingEmail: 'cheese@toast.com',
		} );

		await api.placeOrder( {
			billing_address: {
				first_name: 'Fake',
			},
			shipping_address: {
				first_name: 'Test',
			},
			anythingElse: 'passedThrough',
		} );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'POST',
				path: '/wc/store/v1/checkout/1',
				headers: expect.objectContaining( {
					Nonce: 'global_store_api_nonce',
				} ),
				data: expect.objectContaining( {
					key: 'key_123',
					billing_email: 'cheese@toast.com',
					billing_address: undefined,
					shipping_address: undefined,
					anythingElse: 'passedThrough',
				} ),
			} )
		);
	} );

	it( "backfills the order's missing billing email from the wallet without overwriting existing address fields", async () => {
		const api = new ExpressCheckoutOrderApi( {
			orderId: '1',
			key: 'key_123',
		} );

		// Order created by the merchant without a billing email (e.g. pay-for-order).
		apiFetch.mockResolvedValueOnce( {
			billing_address: {
				first_name: 'Merchant',
				last_name: 'Set',
				address_1: '123 Order St',
				city: 'Orderville',
				country: 'US',
				email: '',
			},
		} );
		await api.getCart();

		await api.placeOrder( {
			billing_address: {
				first_name: 'Wallet',
				last_name: 'Holder',
				address_1: '999 Wallet Ave',
				city: 'Walletville',
				country: 'US',
				email: 'buyer@wallet.com',
			},
		} );

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'POST',
				path: '/wc/store/v1/checkout/1',
				data: expect.objectContaining( {
					// Existing order address values are kept; only the empty email is filled.
					billing_address: {
						first_name: 'Merchant',
						last_name: 'Set',
						address_1: '123 Order St',
						city: 'Orderville',
						country: 'US',
						email: 'buyer@wallet.com',
					},
				} ),
			} )
		);
	} );

	it( 'authorizes a retry with the wallet email after a payment failure on an email-less order', async () => {
		const api = new ExpressCheckoutOrderApi( {
			orderId: '1',
			key: 'key_123',
			billingEmail: '', // Order created without a billing email.
		} );

		apiFetch.mockResolvedValueOnce( {
			billing_address: { first_name: 'Merchant', email: '' },
		} );
		await api.getCart();

		// First attempt: the Store API persists the wallet email, then payment is declined.
		apiFetch.mockRejectedValueOnce( {
			code: 'woocommerce_rest_checkout_process_payment_error',
			message: 'Card declined.',
		} );
		await expect(
			api.placeOrder( {
				billing_address: {
					first_name: 'Wallet',
					email: 'buyer@wallet.com',
				},
			} )
		).rejects.toEqual(
			expect.objectContaining( {
				code: 'woocommerce_rest_checkout_process_payment_error',
			} )
		);

		// Retry: the top-level billing_email now matches the email persisted on the order,
		// so authorization passes instead of returning a 401.
		apiFetch.mockResolvedValueOnce( {} );
		await api.placeOrder( {
			billing_address: {
				first_name: 'Wallet',
				email: 'buyer@wallet.com',
			},
		} );

		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				method: 'POST',
				path: '/wc/store/v1/checkout/1',
				data: expect.objectContaining( {
					billing_email: 'buyer@wallet.com',
					billing_address: expect.objectContaining( {
						first_name: 'Merchant',
						email: 'buyer@wallet.com',
					} ),
				} ),
			} )
		);
	} );

	it( 'places an order with the previous API request data', async () => {
		const api = new ExpressCheckoutOrderApi( {
			orderId: '1',
			key: 'key_123',
			billingEmail: 'cheese@toast.com',
		} );

		apiFetch.mockResolvedValueOnce( {
			billing_address: {
				first_name: 'Fake',
				last_name: 'Test',
			},
			shipping_address: {
				first_name: 'Test',
				last_name: 'Fake',
			},
		} );
		await api.getCart();

		await api.placeOrder( {
			billing_address: {
				first_name: 'Fake',
			},
			shipping_address: {
				first_name: 'Test',
			},
			anythingElse: 'passedThrough',
		} );

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'POST',
				path: '/wc/store/v1/checkout/1',
				headers: expect.objectContaining( {
					Nonce: 'global_store_api_nonce',
				} ),
				data: expect.objectContaining( {
					key: 'key_123',
					billing_email: 'cheese@toast.com',
					billing_address: expect.objectContaining( {
						first_name: 'Fake',
						last_name: 'Test',
					} ),
					shipping_address: expect.objectContaining( {
						first_name: 'Test',
						last_name: 'Fake',
					} ),
					anythingElse: 'passedThrough',
				} ),
			} )
		);
	} );
} );
