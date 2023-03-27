/** @format */

/**
 * Internal dependencies
 */
import {
	getLatestFraudOutcome,
	getLatestFraudOutcomeError,
} from '../selectors';
import { latestFraudOutcomeMock, paymentIntentId } from './hooks';

const latestFraudOutcomeStateMock = {
	data: latestFraudOutcomeMock,
	error: { code: 'error' },
};

const stateMock = {
	fraudOutcomes: {
		latestFraudOutcome: {
			[ paymentIntentId ]: latestFraudOutcomeStateMock,
		},
	},
};

describe( 'Fraud Outcomes selectors', () => {
	describe( 'getLatestFraudOutcome', () => {
		it( 'should return the payment intent data', () => {
			const result = getLatestFraudOutcome( stateMock, paymentIntentId );
			expect( result ).toEqual( latestFraudOutcomeMock );
		} );

		it( 'should return an empty object if the id is not present in the state', () => {
			const result = getLatestFraudOutcome( stateMock, 'not-found' );
			expect( result ).toEqual( undefined );
		} );
	} );

	describe( 'getLatestFraudOutcomeError', () => {
		it( 'should return the payment intent error data', () => {
			const result = getLatestFraudOutcomeError(
				stateMock,
				paymentIntentId
			);
			expect( result ).toEqual( latestFraudOutcomeStateMock.error );
		} );

		it( 'should return an empty object if the id is not present in the state', () => {
			const result = getLatestFraudOutcomeError( stateMock, 'not-found' );
			expect( result ).toEqual( undefined );
		} );
	} );
} );
