/** @format */

/**
 * Internal dependencies
 */
import { getPaymentIntent, getPaymentIntentError } from '../selectors';
import { paymentIntentId, paymentIntentMock } from './hooks';

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
