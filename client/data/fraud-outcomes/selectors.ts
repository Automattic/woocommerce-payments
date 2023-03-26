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
): FraudOutcome =>
	getLatestFraudOutcomeState( state, id )?.data || ( {} as FraudOutcome );

export const getLatestFraudOutcomeError = (
	state: State,
	id: string
): ApiError =>
	getLatestFraudOutcomeState( state, id )?.error || ( {} as ApiError );
