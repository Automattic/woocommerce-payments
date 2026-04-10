/**
 * Internal dependencies
 */
import {
	checkAllExpressMethodsAvailability,
	checkPaymentMethodIsAvailable,
	_resetForTesting,
} from '../checkPaymentMethodIsAvailable';

// Mock the utils index module.
jest.mock( '..', () => ( {
	getExpressCheckoutData: jest.fn( ( key ) => {
		if ( key === 'checkout' ) {
			return { currency_decimals: 2 };
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

	describe( 'checkAllExpressMethodsAvailability', () => {
		it( 'returns all available methods at once', async () => {
			const result = await checkAllExpressMethodsAvailability(
				mockApi,
				1000,
				'usd'
			);

			expect( result ).toEqual( {
				applePay: true,
				googlePay: false,
				amazonPay: true,
			} );
		} );

		it( 'memoizes: same params call stripe.elements() once', async () => {
			await checkAllExpressMethodsAvailability( mockApi, 1000, 'usd' );
			await checkAllExpressMethodsAvailability( mockApi, 1000, 'usd' );

			expect( mockStripe.elements ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'memoizes: different params call stripe.elements() twice', async () => {
			await checkAllExpressMethodsAvailability( mockApi, 1000, 'usd' );
			await checkAllExpressMethodsAvailability( mockApi, 2000, 'eur' );

			expect( mockStripe.elements ).toHaveBeenCalledTimes( 2 );
		} );

		it( 'returns empty object when stripe has error', async () => {
			mockApi.loadStripeForExpressCheckout.mockResolvedValue( {
				error: 'some error',
			} );

			const result = await checkAllExpressMethodsAvailability(
				mockApi,
				1000,
				'usd'
			);

			expect( result ).toEqual( {} );
		} );

		it( 'returns empty object when stripe loading throws', async () => {
			mockApi.loadStripeForExpressCheckout.mockRejectedValue(
				new Error( 'load failed' )
			);

			const result = await checkAllExpressMethodsAvailability(
				mockApi,
				1000,
				'usd'
			);

			expect( result ).toEqual( {} );
		} );

		it( 'returns empty object on loaderror event', async () => {
			mockEceButton.mount.mockImplementation( () => {
				Promise.resolve().then( () => {
					if ( eventHandlers.loaderror ) {
						eventHandlers.loaderror();
					}
				} );
			} );

			const result = await checkAllExpressMethodsAvailability(
				mockApi,
				1000,
				'usd'
			);

			expect( result ).toEqual( {} );
		} );

		it( 'passes mode parameter to stripe.elements()', async () => {
			await checkAllExpressMethodsAvailability(
				mockApi,
				1000,
				'usd',
				'subscription'
			);

			expect( mockStripe.elements ).toHaveBeenCalledWith(
				expect.objectContaining( { mode: 'subscription' } )
			);
		} );

		it( 'defaults mode to payment', async () => {
			await checkAllExpressMethodsAvailability( mockApi, 1000, 'usd' );

			expect( mockStripe.elements ).toHaveBeenCalledWith(
				expect.objectContaining( { mode: 'payment' } )
			);
		} );

		it( 'uses amount of at least 1', async () => {
			await checkAllExpressMethodsAvailability( mockApi, 0, 'usd' );

			expect( mockStripe.elements ).toHaveBeenCalledWith(
				expect.objectContaining( { amount: 1 } )
			);
		} );

		it( 'loads stripe only once across multiple calls', async () => {
			await checkAllExpressMethodsAvailability( mockApi, 1000, 'usd' );
			await checkAllExpressMethodsAvailability( mockApi, 2000, 'eur' );

			expect(
				mockApi.loadStripeForExpressCheckout
			).toHaveBeenCalledTimes( 1 );
		} );
	} );

	describe( 'checkPaymentMethodIsAvailable', () => {
		const createCart = ( totalPrice, currencyCode ) => ( {
			cartTotals: {
				total_price: totalPrice,
				currency_code: currencyCode,
				currency_minor_unit: 2,
			},
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
			expect(
				mockApi.loadStripeForExpressCheckout
			).not.toHaveBeenCalled();
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
	} );
} );
