/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import {
	FraudOutcomesActions,
	FraudOutcomesState,
	UpdateErrorForLatestFraudOutcomeAction,
	UpdateLatestFraudOutcomeAction,
} from './types';

const defaultState = {
	latestFraudOutcome: {},
};

const receiveFraudOutcomes = (
	state: FraudOutcomesState = defaultState,
	action: FraudOutcomesActions
): FraudOutcomesState => {
	const { type, id } = action;

	switch ( type ) {
		case TYPES.SET_LATEST_FRAUD_OUTCOME:
			return {
				...state,
				latestFraudOutcome: {
					[ id ]: {
						...state.latestFraudOutcome[ id ],
						data: ( action as UpdateLatestFraudOutcomeAction ).data,
						error: undefined,
					},
				},
			};
		case TYPES.SET_ERROR_FOR_LATEST_FRAUD_OUTCOME:
			return {
				...state,
				latestFraudOutcome: {
					[ id ]: {
						...state.latestFraudOutcome[ id ],
						data: undefined,
						error: ( action as UpdateErrorForLatestFraudOutcomeAction )
							.error,
					},
				},
			};
		default:
			return state;
	}
};

export default receiveFraudOutcomes;
