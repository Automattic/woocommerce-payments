/** @format */

/**
 * Internal dependencies
 */
import { ApiError } from '../../types/errors';
import { FraudOutcome } from '../../types/fraud-outcome';
import { State } from '../types';
import { LatestFraudOutcomeState } from './types';

const getLatestFraudOutcomeState = ( { fraudOutcomes }: State, id: string ) => {
	return (
		fraudOutcomes?.latestFraudOutcome?.[ id ] ||
		( {} as LatestFraudOutcomeState )
	);
};

export const getLatestFraudOutcome = (
	state: State,
	id: string
): FraudOutcome | undefined => getLatestFraudOutcomeState( state, id )?.data;

export const getLatestFraudOutcomeError = (
	state: State,
	id: string
): ApiError | undefined => getLatestFraudOutcomeState( state, id )?.error;
