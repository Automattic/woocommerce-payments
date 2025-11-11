/** @format */

/**
 * Internal dependencies
 */
import ACTION_TYPES from './action-types';
import { PromotionsState, PromotionsActions } from './types';

const defaultState: PromotionsState = {
	promotions: undefined,
	promotionsError: undefined,
};

export const receivePromotions = (
	state: PromotionsState = defaultState,
	action: PromotionsActions
): PromotionsState => {
	switch ( action.type ) {
		case ACTION_TYPES.SET_PROMOTIONS:
			return {
				...state,
				promotions: action.data,
				promotionsError: undefined,
			};
		case ACTION_TYPES.SET_ERROR_FOR_PROMOTIONS:
			return {
				...state,
				promotions: undefined,
				promotionsError: action.error,
			};
	}

	return state;
};

export default receivePromotions;
