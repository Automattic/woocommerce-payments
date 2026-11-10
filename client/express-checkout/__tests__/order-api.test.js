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
global.wcpayExpressCheckoutParams.nonce.tokenized_cart_nonce =
	'global_tokenized_cart_nonce';

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

	describe( 'when the order has no billing address', () => {
		// The Store API returns every address field, blank on an order created without an address.
		const BLANK_BILLING_ADDRESS = {
			first_name: '',
			last_name: '',
			company: '',
			address_1: '',
			address_2: '',
			city: '',
			state: '',
			postcode: '',
			country: '',
			email: '',
			phone: '',
		};
		const BLANK_SHIPPING_ADDRESS = {
			first_name: '',
			last_name: '',
			company: '',
			address_1: '',
			address_2: '',
			city: '',
			state: '',
			postcode: '',
			country: '',
			phone: '',
		};
		const WALLET_BILLING_ADDRESS = {
			first_name: 'Wallet',
			last_name: 'Holder',
			company: '',
			address_1: '1 Wallet Street',
			address_2: '',
			city: 'London',
			state: '',
			postcode: 'SW1A 1AA',
			country: 'GB',
			email: 'buyer@wallet.com',
			phone: '5551234567',
		};

		const placeOrderFor = async (
			orderAddresses,
			walletBillingAddress = WALLET_BILLING_ADDRESS
		) => {
			const api = new ExpressCheckoutOrderApi( {
				orderId: '1',
				key: 'key_123',
			} );
			apiFetch.mockResolvedValueOnce( orderAddresses );
			await api.getCart();

			await api.placeOrder( {
				billing_address: walletBillingAddress,
			} );

			return apiFetch.mock.lastCall[ 0 ];
		};

		it( "sends the wallet's billing address, and ships to it", async () => {
			const { data, headers } = await placeOrderFor( {
				billing_address: BLANK_BILLING_ADDRESS,
				shipping_address: BLANK_SHIPPING_ADDRESS,
			} );

			expect( data.billing_address ).toEqual( {
				first_name: 'Wallet',
				last_name: 'Holder',
				company: '',
				address_1: '1 Wallet Street',
				address_2: '',
				city: 'London',
				state: '',
				postcode: 'SW1A 1AA',
				country: 'GB',
				email: 'buyer@wallet.com',
				phone: '5551234567',
			} );
			// Sent explicitly: WooCommerce before 11.0 fails when it's missing.
			expect( data.shipping_address ).toEqual( {
				first_name: 'Wallet',
				last_name: 'Holder',
				company: '',
				address_1: '1 Wallet Street',
				address_2: '',
				city: 'London',
				state: '',
				postcode: 'SW1A 1AA',
				country: 'GB',
				phone: '5551234567',
			} );
			// Lets WooPayments normalize the wallet's address, as it does on the cart.
			expect( headers ).toEqual( {
				Nonce: 'global_store_api_nonce',
				'X-WooPayments-Tokenized-Cart': true,
				'X-WooPayments-Tokenized-Cart-Nonce':
					'global_tokenized_cart_nonce',
			} );
		} );

		it( "keeps the order's contact details and replaces any partial location with the wallet's", async () => {
			const { data } = await placeOrderFor( {
				billing_address: {
					...BLANK_BILLING_ADDRESS,
					first_name: 'Merchant',
					last_name: 'Set',
					email: 'shopper@order.com',
					// A location without a country is never kept, so it can't mix with the wallet's.
					city: 'Orderville',
				},
				shipping_address: BLANK_SHIPPING_ADDRESS,
			} );

			expect( data.billing_address ).toEqual( {
				first_name: 'Merchant',
				last_name: 'Set',
				company: '',
				address_1: '1 Wallet Street',
				address_2: '',
				city: 'London',
				state: '',
				postcode: 'SW1A 1AA',
				country: 'GB',
				email: 'shopper@order.com',
				phone: '5551234567',
			} );
			expect( data.shipping_address ).toEqual( {
				first_name: 'Merchant',
				last_name: 'Set',
				company: '',
				address_1: '1 Wallet Street',
				address_2: '',
				city: 'London',
				state: '',
				postcode: 'SW1A 1AA',
				country: 'GB',
				phone: '5551234567',
			} );
		} );

		it( "keeps the contact details of an order's shipping address that has no country", async () => {
			const { data } = await placeOrderFor( {
				billing_address: BLANK_BILLING_ADDRESS,
				shipping_address: {
					...BLANK_SHIPPING_ADDRESS,
					first_name: 'Gift',
					last_name: 'Recipient',
					city: 'Orderville',
				},
			} );

			expect( data.shipping_address ).toEqual( {
				first_name: 'Gift',
				last_name: 'Recipient',
				company: '',
				address_1: '1 Wallet Street',
				address_2: '',
				city: 'London',
				state: '',
				postcode: 'SW1A 1AA',
				country: 'GB',
				phone: '5551234567',
			} );
		} );

		it( 'sends a blank country when the wallet has none, so WooCommerce reports it', async () => {
			const { data } = await placeOrderFor(
				{
					billing_address: BLANK_BILLING_ADDRESS,
					shipping_address: BLANK_SHIPPING_ADDRESS,
				},
				{ ...WALLET_BILLING_ADDRESS, country: undefined }
			);

			expect( data.billing_address.country ).toBe( '' );
			expect( data.shipping_address.country ).toBe( '' );
		} );

		it.each( [
			[
				'the payment fails',
				{ code: 'woocommerce_rest_checkout_process_payment_error' },
			],
			[ 'the order is placed', null ],
		] )(
			"keeps the first wallet's saved address on a retry after %s",
			async ( _, placeOrderError ) => {
				const api = new ExpressCheckoutOrderApi( {
					orderId: '1',
					key: 'key_123',
				} );
				apiFetch.mockResolvedValueOnce( {
					billing_address: BLANK_BILLING_ADDRESS,
					shipping_address: BLANK_SHIPPING_ADDRESS,
				} );
				await api.getCart();
				if ( placeOrderError ) {
					apiFetch.mockRejectedValueOnce( placeOrderError );
				}
				await api
					.placeOrder( { billing_address: WALLET_BILLING_ADDRESS } )
					.catch( () => {} );

				await api.placeOrder( {
					billing_address: {
						...WALLET_BILLING_ADDRESS,
						address_1: '9 Other Street',
						city: 'Paris',
						postcode: '75001',
						country: 'FR',
						email: 'other@wallet.com',
					},
				} );

				const { data, headers } = apiFetch.mock.lastCall[ 0 ];
				expect( data.billing_email ).toBe( 'buyer@wallet.com' );
				expect( data.billing_address ).toEqual( {
					first_name: 'Wallet',
					last_name: 'Holder',
					company: '',
					address_1: '1 Wallet Street',
					address_2: '',
					city: 'London',
					state: '',
					postcode: 'SW1A 1AA',
					country: 'GB',
					email: 'buyer@wallet.com',
					phone: '5551234567',
				} );
				expect( data.shipping_address.country ).toBe( 'GB' );
				expect( headers ).toEqual( {
					Nonce: 'global_store_api_nonce',
				} );
			}
		);

		it( "sends the wallet's address again on a retry after a request rejected before saving", async () => {
			const api = new ExpressCheckoutOrderApi( {
				orderId: '1',
				key: 'key_123',
			} );
			apiFetch.mockResolvedValueOnce( {
				billing_address: BLANK_BILLING_ADDRESS,
				shipping_address: BLANK_SHIPPING_ADDRESS,
			} );
			await api.getCart();
			apiFetch.mockRejectedValueOnce( {
				code: 'woocommerce_rest_invalid_address',
			} );
			await api
				.placeOrder( { billing_address: WALLET_BILLING_ADDRESS } )
				.catch( () => {} );

			await api.placeOrder( {
				billing_address: { ...WALLET_BILLING_ADDRESS, country: 'FR' },
			} );

			const { data } = apiFetch.mock.lastCall[ 0 ];
			expect( data.billing_email ).toBe( '' );
			expect( data.billing_address.country ).toBe( 'FR' );
		} );

		it( "keeps the order's own shipping address", async () => {
			const { data } = await placeOrderFor( {
				billing_address: BLANK_BILLING_ADDRESS,
				shipping_address: {
					...BLANK_SHIPPING_ADDRESS,
					address_1: '2 Order Road',
					city: 'Paris',
					postcode: '75001',
					country: 'FR',
				},
			} );

			expect( data.shipping_address ).toEqual( {
				first_name: '',
				last_name: '',
				company: '',
				address_1: '2 Order Road',
				address_2: '',
				city: 'Paris',
				state: '',
				postcode: '75001',
				country: 'FR',
				phone: '5551234567',
			} );
			expect( data.billing_address.country ).toBe( 'GB' );
		} );
	} );

	it( 'authorizes a later attempt (e.g. switching express method) once the order has been placed', async () => {
		const api = new ExpressCheckoutOrderApi( {
			orderId: '1',
			key: 'key_123',
			billingEmail: '', // Order created without a billing email.
		} );

		apiFetch.mockResolvedValueOnce( {
			billing_address: {
				first_name: 'Merchant',
				country: 'US',
				email: '',
				phone: '',
			},
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

	it.each( [
		'woocommerce_rest_checkout_order_address_change_not_allowed',
		'rest_invalid_param',
	] )(
		'does not remember the email when the request fails with %s',
		async ( code ) => {
			const api = new ExpressCheckoutOrderApi( {
				orderId: '1',
				key: 'key_123',
				billingEmail: '',
			} );
			apiFetch.mockResolvedValueOnce( {
				billing_address: { first_name: 'Merchant', email: '' },
			} );
			await api.getCart();
			apiFetch.mockRejectedValueOnce( { code } );
			await expect(
				api.placeOrder( {
					billing_address: { email: 'buyer@wallet.com' },
				} )
			).rejects.toEqual( { code } );

			apiFetch.mockResolvedValueOnce( {} );
			await api.placeOrder( {
				billing_address: { email: 'buyer@wallet.com' },
			} );

			expect( apiFetch.mock.lastCall[ 0 ].data.billing_email ).toBe( '' );
		}
	);

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
				country: 'US',
			},
			shipping_address: {
				first_name: 'Test',
				last_name: 'Fake',
				country: 'US',
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
				// An order's own address doesn't go through the wallet address normalization.
				headers: {
					Nonce: 'global_store_api_nonce',
				},
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
