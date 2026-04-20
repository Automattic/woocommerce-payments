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

	const createCart = ( totalPrice, currencyCode ) => ( {
		cartTotals: {
			total_price: totalPrice,
			currency_code: currencyCode,
			currency_minor_unit: 2,
		},
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
} );
