/** @format */

/**
 * Internal dependencies
 */
import { PaymentIntent } from '../../../types/payment-intents';
import { getPaymentIntent, getPaymentIntentError } from '../selectors';

const paymentIntentId = 'pi_mock';

const paymentIntentMock: PaymentIntent = {
	id: paymentIntentId,
	amount: 8903,
	currency: 'USD',
	charge: {
		id: 'ch_mock',
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

const paymentIntentStateMock = {
	id: paymentIntentId,
	data: paymentIntentMock,
	error: { code: 'error' },
};

const stateMock = {
	paymentIntents: {
		[ paymentIntentId ]: paymentIntentStateMock,
	},
};

describe( 'Payment Intents selectors', () => {
	describe( 'getPaymentIntent', () => {
		it( 'should return the payment intent data', () => {
			const result = getPaymentIntent( stateMock, paymentIntentId );
			expect( result ).toEqual( paymentIntentStateMock.data );
		} );

		it( 'should return an empty object if the id is not present in the state', () => {
			const result = getPaymentIntent( stateMock, 'not-found' );
			expect( result ).toEqual( {} );
		} );
	} );

	describe( 'getPaymentIntentError', () => {
		it( 'should return the payment intent error data', () => {
			const result = getPaymentIntentError( stateMock, paymentIntentId );
			expect( result ).toEqual( paymentIntentStateMock.error );
		} );

		it( 'should return an empty object if the id is not present in the state', () => {
			const result = getPaymentIntentError( stateMock, 'not-found' );
			expect( result ).toEqual( {} );
		} );
	} );
} );
