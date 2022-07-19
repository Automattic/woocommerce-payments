/** @format */

/**
 * Internal dependencies
 */
import { usePaymentIntentWithChargeFallback } from '../';
import { STORE_NAME } from '../../constants';
import { useSelect } from '@wordpress/data';
import { PaymentIntent } from '../../../types/payment-intents';

jest.mock( '@wordpress/data' );

const paymentIntentId = 'pi_mock';
const chargeId = 'ch_mock';
const paymentIntentMock: PaymentIntent = {
	id: paymentIntentId,
	amount: 8903,
	currency: 'USD',
	charge: {
		id: chargeId,
		amount: 8903,
		created: 1656701170,
		payment_method_details: {
			card: {},
			type: 'card',
		},
	},
	created: 1656701169,
	customer: 'cus_mock',
	metadata: {},
	payment_method: 'pm_mock',
	status: 'requires_capture',
};

describe( 'Payment Intent hooks', () => {
	let selectors: Record< string, () => any >;

	beforeEach( () => {
		selectors = {};

		const selectMock = jest.fn( ( storeName ) =>
			STORE_NAME === storeName ? selectors : {}
		);

		( useSelect as jest.Mock ).mockImplementation(
			( cb: ( callback: any ) => jest.Mock ) => cb( selectMock )
		);
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
			};

			const result = usePaymentIntentWithChargeFallback( chargeId );

			expect( selectors.getPaymentIntent ).not.toHaveBeenCalled();
			expect( selectors.getCharge ).toHaveBeenCalledWith( chargeId );

			expect( result ).toEqual( {
				data: paymentIntentMock.charge,
				error: {},
				isLoading: false,
			} );
		} );

		it( 'should return the correct data if a payment intent id is provided', async () => {
			selectors = {
				getPaymentIntent: jest
					.fn()
					.mockReturnValue( paymentIntentMock ),
				getCharge: jest
					.fn()
					.mockReturnValue( paymentIntentMock.charge ),
				getChargeError: jest.fn().mockReturnValue( {} ),
				isResolving: jest.fn().mockReturnValue( false ),
			};

			const result = usePaymentIntentWithChargeFallback(
				paymentIntentId
			);

			expect( selectors.getPaymentIntent ).toHaveBeenCalledWith(
				paymentIntentId
			);
			expect( selectors.getCharge ).toHaveBeenCalledWith( chargeId );

			expect( result ).toEqual( {
				data: paymentIntentMock.charge,
				error: {},
				isLoading: false,
			} );
		} );

		it( 'should return an empty object if there is no payment intent data yet', async () => {
			selectors = {
				getCharge: jest.fn().mockReturnValue( {} ),
				isResolving: jest.fn().mockReturnValue( true ),
				getPaymentIntent: jest.fn().mockReturnValue( {} ),
				getPaymentIntentError: jest.fn().mockReturnValue( {} ),
			};

			const result = usePaymentIntentWithChargeFallback(
				paymentIntentId
			);

			expect( selectors.getPaymentIntent ).toHaveBeenCalledWith(
				paymentIntentId
			);
			expect( selectors.getCharge ).not.toHaveBeenCalled();

			expect( result ).toEqual( {
				data: {},
				error: {},
				isLoading: true,
			} );
		} );
	} );
} );
