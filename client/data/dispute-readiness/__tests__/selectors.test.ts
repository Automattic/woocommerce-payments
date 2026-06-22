/** @format */

/**
 * Internal dependencies
 */
import { getDisputeReadiness, getDisputeReadinessError } from '../selectors';
import { DisputeReadinessData } from '../types';
import { ApiError } from '../../../types/errors';

const readinessPayload: DisputeReadinessData = {
	overview: {
		enabled: true,
		hidden: false,
		score: 3,
		total: 4,
		state: 'incomplete',
		isDismissed: false,
		completeSignalIds: [],
		incompleteSignalIds: [],
		signals: [],
	},
};
const error: ApiError = { code: 'error' };

const state = {
	disputeReadiness: {
		disputeReadiness: readinessPayload,
		disputeReadinessError: error,
	},
};

describe( 'dispute readiness selectors', () => {
	it( 'returns dispute readiness data', () => {
		expect( getDisputeReadiness( state ) ).toEqual( readinessPayload );
	} );

	it( 'returns dispute readiness errors', () => {
		expect( getDisputeReadinessError( state ) ).toEqual( error );
	} );
} );
