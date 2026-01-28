/** @format */

/**
 * Internal dependencies
 */
import { ACTION_TYPES } from './action-types';
import { PmPromotionsState, PmPromotionsActions } from './types';

const defaultState: PmPromotionsState = {
	pmPromotions: undefined,
	pmPromotionsError: undefined,
};

export const receivePmPromotions = (
	state: PmPromotionsState = defaultState,
	action: PmPromotionsActions
): PmPromotionsState => {
	switch ( action.type ) {
		case ACTION_TYPES.SET_PM_PROMOTIONS:
			return {
				...state,
				pmPromotions: action.data,
				pmPromotionsError: undefined,
			};
		case ACTION_TYPES.SET_ERROR_FOR_PM_PROMOTIONS:
			return {
				...state,
				pmPromotions: undefined,
				pmPromotionsError: action.error,
			};
	}

	return state;
};

export default receivePmPromotions;
