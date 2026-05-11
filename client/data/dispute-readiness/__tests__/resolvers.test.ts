/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getDisputeReadiness } from '../resolvers';
import { DisputeReadinessData } from '../types';
import { ApiError } from '../../../types/errors';

const readinessPayload: DisputeReadinessData = {
	overview: {
		enabled: true,
		hidden: false,
		score: 2,
		total: 4,
		state: 'incomplete',
		isDismissed: false,
		completeSignalIds: [],
		incompleteSignalIds: [],
		signals: [],
	},
};
const errorResponse: ApiError = { code: 'error' };

describe( 'getDisputeReadiness resolver', () => {
	let generator: Generator< unknown, unknown, unknown >;

	beforeEach( () => {
		generator = getDisputeReadiness() as Generator<
			unknown,
			unknown,
			unknown
		>;
		expect( generator.next().value ).toEqual(
			apiFetch( {
				path: '/wc/v3/payments/dispute-readiness',
			} )
		);
	} );

	afterEach( () => {
		expect( generator.next().done ).toStrictEqual( true );
	} );

	test( 'updates dispute readiness on success', () => {
		expect( generator.next( readinessPayload ).value ).toEqual(
			controls.dispatch(
				'wc/payments',
				'updateDisputeReadiness',
				readinessPayload
			)
		);
	} );

	test( 'updates dispute readiness error on failure', () => {
		expect( generator.throw( errorResponse ).value ).toEqual(
			controls.dispatch(
				'wc/payments',
				'updateErrorForDisputeReadiness',
				errorResponse
			)
		);
	} );
} );
