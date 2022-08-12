/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { usePaymentIntentWithChargeFallback } from '../';
import { STORE_NAME } from '../../constants';
import { OutcomeRiskLevel } from '../../../types/charges';
import { PaymentIntent } from '../../../types/payment-intents';

jest.mock( '@wordpress/data' );

export const chargeId = 'ch_mock';

export const paymentIntentId = 'pi_mock';

export const paymentIntentMock: PaymentIntent = {
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
		payment_method: 'pm_mock',
		amount_captured: 8903,
		amount_refunded: 8903,
		application_fee_amount: 82,
		balance_transaction: {
			fee: 82,
			amount: 8903,
			currency: 'usd',
		},
		billing_details: {
			address: {
				city: 'City',
				country: 'US',
				line1: 'Line 1',
				line2: 'Line 2',
				postal_code: 'Postal code',
				state: 'State',
			},
			email: 'admin@example.com',
			name: 'First Name',
			phone: '0-000-000-0000',
			formatted_address: 'Line 1<br/>Line 2<br/>City, State Postal code',
		},
		currency: 'usd',
		dispute: null,
		disputed: false,
		order: {
			number: Number( '67' ),
			url: 'http://order.url',
			customer_url: 'customer.url',
			subscriptions: [],
		},
		outcome: {
			network_status: 'approved_by_network',
			reason: null,
			risk_level: 'normal' as OutcomeRiskLevel,
			risk_score: 56,
			seller_message: 'Payment complete.',
			type: 'authorized',
		},
		paid: true,
		paydown: null,
		payment_intent: paymentIntentId,
		refunded: true,
		refunds: null,
		status: 'succeeded',
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
				isResolving: jest.fn().mockReturnValue( false ),
				getPaymentIntent: jest
					.fn()
					.mockReturnValue( paymentIntentMock ),
				getPaymentIntentError: jest.fn().mockReturnValue( {} ),
			};

			const result = usePaymentIntentWithChargeFallback(
				paymentIntentId
			);

			expect( selectors.getPaymentIntent ).toHaveBeenCalledWith(
				paymentIntentId
			);

			expect( result ).toEqual( {
				data: paymentIntentMock.charge,
				error: {},
				isLoading: false,
			} );
		} );

		it( 'should return an empty object if there is no payment intent data yet', async () => {
			selectors = {
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

			expect( result ).toEqual( {
				data: {},
				error: {},
				isLoading: true,
			} );
		} );
	} );
} );
