/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { usePaymentIntentWithChargeFallback } from '../';
import { chargeId, paymentIntentId, paymentIntentMock } from '../__fixtures__';

jest.mock( '@wordpress/data' );

describe( 'Payment Intent hooks', () => {
	let selectors: Record< string, () => any >;

	beforeEach( () => {
		selectors = {};

		// The hook reads from the charges and payment-intents stores (passed as
		// descriptors); each test sets up only the selectors its branch needs,
		// so return them regardless of which store is selected.
		const selectMock = jest.fn( () => selectors );

		( useSelect as jest.Mock ).mockImplementation(
			( cb: ( callback: any ) => jest.Mock ) => cb( selectMock )
		);

		jest.spyOn(
			// eslint-disable-next-line @typescript-eslint/no-var-requires
			require( '@wordpress/data' ),
			'useDispatch'
		).mockReturnValue( () => {
			return {
				refundCharge: jest.fn(), // Mock the refundCharge function
			};
		} );
	} );

	describe( 'usePaymentIntentWithChargeFallback', () => {
		it( 'should return the correct data if a charge id is provided', async () => {
			selectors = {
				getPaymentIntent: jest
					.fn()
					.mockReturnValue( paymentIntentMock ),
				getCharge: jest
					.fn()
					.mockReturnValue( paymentIntentMock.charge ),
				getChargeError: jest.fn().mockReturnValue( {} ),
				isResolving: jest.fn().mockReturnValue( false ),
				hasFinishedResolution: jest.fn().mockReturnValue( true ),
			};

			const result = usePaymentIntentWithChargeFallback( chargeId );

			expect( selectors.getPaymentIntent ).not.toHaveBeenCalled();
			expect( selectors.getCharge ).toHaveBeenCalledWith( chargeId );

			expect( result ).toEqual( {
				data: paymentIntentMock.charge,
				doRefund: expect.any( Function ),
				error: {},
				isLoading: false,
			} );
		} );

		it( 'should return the correct data if a payment intent id is provided', async () => {
			selectors = {
				isResolving: jest.fn().mockReturnValue( false ),
				getPaymentIntent: jest
					.fn()
					.mockReturnValue( paymentIntentMock ),
				getPaymentIntentError: jest.fn().mockReturnValue( {} ),
				hasFinishedResolution: jest.fn().mockReturnValue( true ),
			};

			const result =
				usePaymentIntentWithChargeFallback( paymentIntentId );

			expect( selectors.getPaymentIntent ).toHaveBeenCalledWith(
				paymentIntentId
			);

			expect( result ).toEqual( {
				data: paymentIntentMock,
				doRefund: expect.any( Function ),
				error: {},
				isLoading: false,
			} );
		} );

		it( 'should return an empty object if there is no payment intent data yet', async () => {
			selectors = {
				isResolving: jest.fn().mockReturnValue( true ),
				getPaymentIntent: jest.fn().mockReturnValue( {} ),
				getPaymentIntentError: jest.fn().mockReturnValue( {} ),
				hasFinishedResolution: jest.fn().mockReturnValue( false ),
			};

			const result =
				usePaymentIntentWithChargeFallback( paymentIntentId );

			expect( selectors.getPaymentIntent ).toHaveBeenCalledWith(
				paymentIntentId
			);

			expect( result ).toEqual( {
				data: {},
				doRefund: expect.any( Function ),
				error: {},
				isLoading: true,
			} );
		} );
	} );
} );
