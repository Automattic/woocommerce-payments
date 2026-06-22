/** @format */

/**
 * Internal dependencies
 */
import { ApiError } from '../../types/errors';
import { DisputeReadinessData, DisputeReadinessState } from './types';

interface State {
	disputeReadiness: DisputeReadinessState;
}

export const getDisputeReadiness = (
	state: State
): DisputeReadinessData | undefined => state.disputeReadiness?.disputeReadiness;

export const getDisputeReadinessError = (
	state: State
): ApiError | undefined => state.disputeReadiness?.disputeReadinessError;
