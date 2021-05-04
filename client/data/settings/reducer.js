/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';

const defaultState = {
	isSaving: false,
};

export const receiveSettings = (
	state = defaultState,
	{ type, ...action }
) => {
	switch ( type ) {
		case TYPES.SET_SETTINGS:
			return {
				data: {
					...action.data,
				},
			};

		case TYPES.SET_IS_SAVING_SETTINGS:
			return {
				...state,
				isSaving: action.isSaving,
			};

		case TYPES.SET_ENABLED_PAYMENT_METHOD_IDS:
			return {
				...state,
				data: {
					// eslint-disable-next-line camelcase
					enabled_payment_method_ids: action.methodIds,
				},
			};
	}

	return state;
};

export default receiveSettings;
