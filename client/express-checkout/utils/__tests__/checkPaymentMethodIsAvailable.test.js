/**
 * Internal dependencies
 */
import {
	checkPaymentMethodIsAvailable,
	_resetForTesting,
} from '../checkPaymentMethodIsAvailable';

// Mock the utils index module.
jest.mock( '..', () => ( {
	getExpressCheckoutData: jest.fn( ( key ) => {
		if ( key === 'checkout' ) {
			return { currency_decimals: 2 };
		}
		if ( key === 'enabled_methods' ) {
			return [ 'payment_request', 'amazon_pay' ];
		}
		return null;
	} ),
	filterCartMethodsByLocation: jest.fn( ( methods ) => methods ),
} ) );

jest.mock( '../../transformers/wc-to-stripe', () => ( {
	transformPrice: jest.fn( ( price ) => price ),
} ) );

jest.mock( '@wordpress/hooks', () => ( {
	applyFilters: jest.fn( ( _, value ) => value ),
} ) );

describe( 'checkPaymentMethodIsAvailable', () => {
	let mockApi;
	let mockEceButton;
	let mockElements;
	let mockStripe;
	let eventHandlers;

	const createCart = ( totalPrice, currencyCode, extensions = {} ) => ( {
		cartTotals: {
			total_price: totalPrice,
			currency_code: currencyCode,
			currency_minor_unit: 2,
		},
		cartItems: [],
		extensions,
	} );

	beforeEach( () => {
		_resetForTesting();
		eventHandlers = {};

		mockEceButton = {
			on: jest.fn( ( event, handler ) => {
				eventHandlers[ event ] = handler;
			} ),
			mount: jest.fn( () => {
				// Trigger the ready event asynchronously after mount.
				Promise.resolve().then( () => {
					if ( eventHandlers.ready ) {
						eventHandlers.ready( {
							availablePaymentMethods: {
								applePay: true,
								googlePay: false,
								amazonPay: true,
							},
						} );
					}
				} );
			} ),
			unmount: jest.fn(),
		};

		mockElements = {
			create: jest.fn( () => mockEceButton ),
		};

		mockStripe = {
			elements: jest.fn( () => mockElements ),
		};

		mockApi = {
			loadStripeForExpressCheckout: jest
				.fn()
				.mockResolvedValue( mockStripe ),
		};
	} );

	it( 'returns true for available method', async () => {
		const result = await checkPaymentMethodIsAvailable(
			'applePay',
			createCart( '1000', 'USD' ),
			mockApi
		);

		expect( result ).toBe( true );
	} );

	it( 'returns false for unavailable method', async () => {
		const result = await checkPaymentMethodIsAvailable(
			'googlePay',
			createCart( '1000', 'USD' ),
			mockApi
		);

		expect( result ).toBe( false );
	} );

	it( 're-probes instead of reusing a false result when an empty cart gets its first product', async () => {
		mockEceButton.mount.mockImplementationOnce( () => {
			Promise.resolve().then( () =>
				eventHandlers.ready( { availablePaymentMethods: undefined } )
			);
		} );

		const emptyCartResult = await checkPaymentMethodIsAvailable(
			'applePay',
			createCart( '0', 'EUR' ),
			mockApi
		);
		const filledCartResult = await checkPaymentMethodIsAvailable(
			'applePay',
			createCart( '28000', 'EUR' ),
			mockApi
		);

		expect( emptyCartResult ).toBe( false );
		expect( filledCartResult ).toBe( true );
		expect( mockStripe.elements ).toHaveBeenCalledTimes( 2 );
		expect( mockStripe.elements ).toHaveBeenLastCalledWith(
			expect.objectContaining( { amount: 28000, currency: 'eur' } )
		);
	} );

	it( 'returns false immediately when currency code is empty', async () => {
		const result = await checkPaymentMethodIsAvailable(
			'applePay',
			createCart( '1000', '' ),
			mockApi
		);

		expect( result ).toBe( false );
		// Should not attempt to load Stripe at all.
		expect( mockApi.loadStripeForExpressCheckout ).not.toHaveBeenCalled();
	} );

	it( 'returns false when stripe has error', async () => {
		mockApi.loadStripeForExpressCheckout.mockResolvedValue( {
			error: 'some error',
		} );

		const result = await checkPaymentMethodIsAvailable(
			'applePay',
			createCart( '1000', 'USD' ),
			mockApi
		);

		expect( result ).toBe( false );
	} );

	it( 'returns false when stripe loading throws', async () => {
		mockApi.loadStripeForExpressCheckout.mockRejectedValue(
			new Error( 'load failed' )
		);

		const result = await checkPaymentMethodIsAvailable(
			'applePay',
			createCart( '1000', 'USD' ),
			mockApi
		);

		expect( result ).toBe( false );
	} );

	it( 'returns false on loaderror event', async () => {
		mockEceButton.mount.mockImplementation( () => {
			Promise.resolve().then( () => {
				if ( eventHandlers.loaderror ) {
					eventHandlers.loaderror();
				}
			} );
		} );

		const result = await checkPaymentMethodIsAvailable(
			'applePay',
			createCart( '1000', 'USD' ),
			mockApi
		);

		expect( result ).toBe( false );
	} );

	it( 'different methods with same cart share one check', async () => {
		await checkPaymentMethodIsAvailable(
			'applePay',
			createCart( '1000', 'USD' ),
			mockApi
		);
		await checkPaymentMethodIsAvailable(
			'googlePay',
			createCart( '1000', 'USD' ),
			mockApi
		);

		// Only one stripe.elements() call since both share the same check.
		expect( mockStripe.elements ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'different cart totals trigger separate checks', async () => {
		await checkPaymentMethodIsAvailable(
			'applePay',
			createCart( '1000', 'USD' ),
			mockApi
		);
		await checkPaymentMethodIsAvailable(
			'applePay',
			createCart( '2000', 'EUR' ),
			mockApi
		);

		expect( mockStripe.elements ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'loads stripe only once across multiple calls', async () => {
		await checkPaymentMethodIsAvailable(
			'applePay',
			createCart( '1000', 'USD' ),
			mockApi
		);
		await checkPaymentMethodIsAvailable(
			'applePay',
			createCart( '2000', 'EUR' ),
			mockApi
		);

		expect( mockApi.loadStripeForExpressCheckout ).toHaveBeenCalledTimes(
			1
		);
	} );

	it( 'uses amount of at least 1', async () => {
		await checkPaymentMethodIsAvailable(
			'applePay',
			createCart( '0', 'USD' ),
			mockApi
		);

		expect( mockStripe.elements ).toHaveBeenCalledWith(
			expect.objectContaining( { amount: 1 } )
		);
	} );

	// This probe builds its own Elements instance to ask which wallets are available. If its
	// setupFutureUsage diverges from the one the real button mints with, availability is
	// answered for a different payment than the shopper actually makes.
	describe( 'setupFutureUsage', () => {
		afterEach( () => {
			delete global.wcpayExpressCheckoutParams;
		} );

		it( 'passes the value the server declared on the cart', async () => {
			await checkPaymentMethodIsAvailable(
				'applePay',
				createCart( '1000', 'USD', {
					wcpay: { setup_future_usage: 'off_session' },
				} ),
				mockApi
			);

			expect( mockStripe.elements ).toHaveBeenCalledWith(
				expect.objectContaining( { setupFutureUsage: 'off_session' } )
			);
		} );

		it( 'omits it when the server declared none', async () => {
			await checkPaymentMethodIsAvailable(
				'applePay',
				createCart( '1000', 'USD', {
					wcpay: { setup_future_usage: null },
				} ),
				mockApi
			);

			expect( mockStripe.elements ).toHaveBeenCalledWith(
				expect.not.objectContaining( {
					setupFutureUsage: expect.anything(),
				} )
			);
		} );

		// The page-load globals go stale the moment the shopper edits the cart without a
		// reload, so the live cart has to win.
		it( 'reads the live cart rather than the page-load globals', async () => {
			global.wcpayExpressCheckoutParams = {
				setup_future_usage: 'off_session',
			};

			await checkPaymentMethodIsAvailable(
				'applePay',
				createCart( '1000', 'USD', {
					wcpay: { setup_future_usage: null },
				} ),
				mockApi
			);

			expect( mockStripe.elements ).toHaveBeenCalledWith(
				expect.not.objectContaining( {
					setupFutureUsage: expect.anything(),
				} )
			);
		} );

		// Holding amount and currency fixed is what makes this discriminating: it leaves
		// setupFutureUsage as the only dimension that can force a second probe. Swapping a
		// simple product for a same-priced subscription without a reload is the real case —
		// without it in the memo key, the cached probe answers for the wrong payment shape.
		it( 'different setupFutureUsage triggers separate checks', async () => {
			await checkPaymentMethodIsAvailable(
				'applePay',
				createCart( '1000', 'USD', {
					wcpay: { setup_future_usage: null },
				} ),
				mockApi
			);
			await checkPaymentMethodIsAvailable(
				'applePay',
				createCart( '1000', 'USD', {
					wcpay: { setup_future_usage: 'off_session' },
				} ),
				mockApi
			);

			expect( mockStripe.elements ).toHaveBeenCalledTimes( 2 );
		} );
	} );

	describe( 'paymentMethodTypes', () => {
		// Amazon Pay only supports USD, and Stripe fails the whole check when one
		// type doesn't support the currency. After a mid-page switch to EUR, the
		// page-load list still has Amazon Pay; the cart's list doesn't.
		it( 'reads the live cart rather than the page-load globals', async () => {
			await checkPaymentMethodIsAvailable(
				'googlePay',
				createCart( '1000', 'EUR', {
					wcpay: { express_checkout_methods: [ 'payment_request' ] },
				} ),
				mockApi
			);

			expect( mockStripe.elements ).toHaveBeenCalledWith(
				expect.objectContaining( { paymentMethodTypes: [ 'card' ] } )
			);
		} );

		it( 'falls back to the page-load globals when the cart has no list', async () => {
			await checkPaymentMethodIsAvailable(
				'googlePay',
				createCart( '1000', 'USD' ),
				mockApi
			);

			expect( mockStripe.elements ).toHaveBeenCalledWith(
				expect.objectContaining( {
					paymentMethodTypes: [ 'card', 'amazon_pay' ],
				} )
			);
		} );

		it( 'different enabled methods trigger separate checks', async () => {
			await checkPaymentMethodIsAvailable(
				'googlePay',
				createCart( '1000', 'USD', {
					wcpay: { express_checkout_methods: [ 'payment_request' ] },
				} ),
				mockApi
			);
			await checkPaymentMethodIsAvailable(
				'googlePay',
				createCart( '1000', 'USD', {
					wcpay: {
						express_checkout_methods: [
							'payment_request',
							'amazon_pay',
						],
					},
				} ),
				mockApi
			);

			expect( mockStripe.elements ).toHaveBeenCalledTimes( 2 );
		} );
	} );
} );
