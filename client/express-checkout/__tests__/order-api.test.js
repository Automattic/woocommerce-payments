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

	it( "backfills the order's missing contact details from the wallet, keeping existing address fields", async () => {
		const api = new ExpressCheckoutOrderApi( {
			orderId: '1',
			key: 'key_123',
		} );

		// Order created by the merchant with an address but no email or phone (e.g. pay-for-order).
		apiFetch.mockResolvedValueOnce( {
			billing_address: {
				first_name: 'Merchant',
				last_name: 'Set',
				address_1: '123 Order St',
				city: 'Orderville',
				country: 'US',
				email: '',
				phone: '',
			},
			shipping_address: {
				first_name: 'Merchant',
				last_name: 'Set',
				address_1: '123 Order St',
				city: 'Orderville',
				country: 'US',
				phone: '',
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
				phone: '5551234567',
			},
		} );

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'POST',
				path: '/wc/store/v1/checkout/1',
				data: expect.objectContaining( {
					// Existing address values are kept; only empty contact fields are filled.
					billing_address: {
						first_name: 'Merchant',
						last_name: 'Set',
						address_1: '123 Order St',
						city: 'Orderville',
						country: 'US',
						email: 'buyer@wallet.com',
						phone: '5551234567',
					},
					shipping_address: {
						first_name: 'Merchant',
						last_name: 'Set',
						address_1: '123 Order St',
						city: 'Orderville',
						country: 'US',
						phone: '5551234567',
					},
				} ),
			} )
		);
	} );

	it( 'replays addresses from server-hydrated order data at placement, without an initial fetch', async () => {
		const api = new ExpressCheckoutOrderApi( {
			orderId: '1',
			key: 'key_123',
		} );

		// Seeded from the page-render hydration instead of a GET /order/{id} fetch.
		api.prefillCart( {
			id: 1,
			billing_address: {
				first_name: 'Merchant',
				last_name: 'Set',
				email: '',
				phone: '',
			},
			shipping_address: { first_name: 'Merchant', last_name: 'Set' },
		} );

		apiFetch.mockResolvedValueOnce( {} );

		await api.placeOrder( {
			billing_address: { email: 'buyer@wallet.com', phone: '5551234567' },
		} );

		// Only the placement POST — the seeded data supplied the addresses, so no GET fired.
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'POST',
				path: '/wc/store/v1/checkout/1',
				data: expect.objectContaining( {
					billing_address: expect.objectContaining( {
						first_name: 'Merchant',
						email: 'buyer@wallet.com',
						phone: '5551234567',
					} ),
					shipping_address: expect.objectContaining( {
						first_name: 'Merchant',
						phone: '5551234567',
					} ),
				} ),
			} )
		);
	} );

	it( 'authorizes a later attempt (e.g. switching express method) once the order has been placed', async () => {
		const api = new ExpressCheckoutOrderApi( {
			orderId: '1',
			key: 'key_123',
			billingEmail: '', // Order created without a billing email.
		} );

		apiFetch.mockResolvedValueOnce( {
			billing_address: { first_name: 'Merchant', email: '', phone: '' },
		} );
		await api.getCart();

		// First attempt (e.g. Google Pay): the order is placed - so the Store API persists the
		// email - but the card is declined later during client-side confirmation, so placeOrder
		// still resolves.
		apiFetch.mockResolvedValueOnce( {
			payment_result: { payment_status: 'failure' },
		} );
		await api.placeOrder( {
			billing_address: { email: 'buyer@gpay.com', phone: '5550001111' },
		} );

		// Second attempt (e.g. Amazon Pay): authorizes with the email now stored on the order,
		// rather than the empty top-level billing_email that would trigger a 401.
		apiFetch.mockResolvedValueOnce( {
			payment_result: { payment_status: 'success' },
		} );
		await api.placeOrder( {
			billing_address: { email: 'buyer@amazon.com', phone: '5550001111' },
		} );

		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				method: 'POST',
				path: '/wc/store/v1/checkout/1',
				data: expect.objectContaining( {
					billing_email: 'buyer@gpay.com',
					billing_address: expect.objectContaining( {
						email: 'buyer@amazon.com',
					} ),
				} ),
			} )
		);
	} );

	it( 'remembers the wallet email after a server-side payment failure, so a retry authorizes', async () => {
		const api = new ExpressCheckoutOrderApi( {
			orderId: '1',
			key: 'key_123',
			billingEmail: '', // Order created without a billing email.
		} );

		apiFetch.mockResolvedValueOnce( {
			billing_address: { first_name: 'Merchant', email: '' },
		} );
		await api.getCart();

		// First attempt: the Store API persists the wallet email, then the payment throws.
		apiFetch.mockRejectedValueOnce( {
			code: 'woocommerce_rest_checkout_process_payment_error',
			message: 'Card declined.',
		} );
		await expect(
			api.placeOrder( {
				billing_address: { email: 'buyer@wallet.com' },
			} )
		).rejects.toEqual(
			expect.objectContaining( {
				code: 'woocommerce_rest_checkout_process_payment_error',
			} )
		);

		// Retry: the top-level billing_email now matches the email persisted on the order.
		apiFetch.mockResolvedValueOnce( {} );
		await api.placeOrder( {
			billing_address: { email: 'buyer@wallet.com' },
		} );

		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				method: 'POST',
				path: '/wc/store/v1/checkout/1',
				data: expect.objectContaining( {
					billing_email: 'buyer@wallet.com',
				} ),
			} )
		);
	} );

	it( 'remembers the wallet email when a declined payment rejects without an error code', async () => {
		const api = new ExpressCheckoutOrderApi( {
			orderId: '1',
			key: 'key_123',
			billingEmail: '', // Order created without a billing email.
		} );

		apiFetch.mockResolvedValueOnce( {
			billing_address: { first_name: 'Merchant', email: '' },
		} );
		await api.getCart();

		// A declined wallet payment can reject with an unparsed response, so `error.code` is
		// undefined - but the order has already been stamped with the email before payment.
		apiFetch.mockRejectedValueOnce( { status: 400 } );
		await expect(
			api.placeOrder( {
				billing_address: { email: 'buyer@wallet.com' },
			} )
		).rejects.toEqual( expect.objectContaining( { status: 400 } ) );

		// Retry authorizes with the email persisted on the order.
		apiFetch.mockResolvedValueOnce( {} );
		await api.placeOrder( {
			billing_address: { email: 'buyer@wallet.com' },
		} );

		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				data: expect.objectContaining( {
					billing_email: 'buyer@wallet.com',
				} ),
			} )
		);
	} );

	it( 'does not remember the email when the request fails before it is persisted', async () => {
		const api = new ExpressCheckoutOrderApi( {
			orderId: '1',
			key: 'key_123',
			billingEmail: '', // Order created without a billing email.
		} );

		apiFetch.mockResolvedValueOnce( {
			billing_address: { first_name: 'Merchant', email: '' },
		} );
		await api.getCart();

		// Address validation fails before the email is persisted to the order.
		apiFetch.mockRejectedValueOnce( {
			code: 'woocommerce_rest_invalid_address',
			message: 'Phone is required.',
		} );
		await expect(
			api.placeOrder( {
				billing_address: { email: 'buyer@wallet.com' },
			} )
		).rejects.toEqual(
			expect.objectContaining( {
				code: 'woocommerce_rest_invalid_address',
			} )
		);

		// Retry still authorizes with the order's (unchanged) empty email, surfacing the real error.
		apiFetch.mockRejectedValueOnce( {
			code: 'woocommerce_rest_invalid_address',
			message: 'Phone is required.',
		} );
		await expect(
			api.placeOrder( {
				billing_address: { email: 'buyer@wallet.com' },
			} )
		).rejects.toEqual(
			expect.objectContaining( {
				code: 'woocommerce_rest_invalid_address',
			} )
		);

		expect( apiFetch ).toHaveBeenLastCalledWith(
			expect.objectContaining( {
				data: expect.objectContaining( { billing_email: '' } ),
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
