/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { getLatestFraudOutcome } from '../resolvers';
import { paymentIntentId, latestFraudOutcomeMock } from './hooks';
import {
	updateErrorForLatestFraudOutcome,
	updateLatestFraudOutcome,
} from '../actions';

const errorResponse = { code: 'error' };

describe( 'getLatestFraudOutcome resolver', () => {
	let generator: Generator< unknown >;

	beforeEach( () => {
		generator = getLatestFraudOutcome( paymentIntentId );
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: `/wc/v3/payments/fraud_outcomes/${ paymentIntentId }/latest`,
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	describe( 'on success', () => {
		test( 'should update state with fraud outcome data', () => {
			expect( generator.next( latestFraudOutcomeMock ).value ).toEqual(
				updateLatestFraudOutcome(
					paymentIntentId,
					latestFraudOutcomeMock
				)
			);
		} );
	} );

	describe( 'on error', () => {
		test( 'should update state with error', () => {
			expect( generator.throw( errorResponse ).value ).toEqual(
				updateErrorForLatestFraudOutcome(
					paymentIntentId,
					errorResponse
				)
			);
		} );
	} );
} );
