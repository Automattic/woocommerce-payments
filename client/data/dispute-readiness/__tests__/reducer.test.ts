/** @format */

/**
 * Internal dependencies
 */
import reducer from '../reducer';
import { ACTION_TYPES } from '../action-types';
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
		completeSignalIds: [ 'refund_policy', 'support_contact' ],
		incompleteSignalIds: [ 'statement_descriptor', 'terms_and_conditions' ],
		signals: [],
	},
};

describe( 'dispute readiness reducer', () => {
	it( 'stores dispute readiness payload and clears previous errors', () => {
		const state = reducer(
			{
				disputeReadiness: undefined,
				disputeReadinessError: { code: 'previous_error' },
			},
			{
				type: ACTION_TYPES.SET_DISPUTE_READINESS,
				data: readinessPayload,
			}
		);

		expect( state.disputeReadiness ).toEqual( readinessPayload );
		expect( state.disputeReadinessError ).toBeUndefined();
	} );

	it( 'stores dispute readiness errors', () => {
		const error: ApiError = { code: 'error' };
		const state = reducer( undefined, {
			type: ACTION_TYPES.SET_ERROR_FOR_DISPUTE_READINESS,
			error,
		} );

		expect( state.disputeReadinessError ).toEqual( error );
	} );
} );
