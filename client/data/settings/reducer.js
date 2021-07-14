/** @format */

/**
 * Internal dependencies
 */
import ACTION_TYPES from './action-types';

const defaultState = {
	isSaving: false,
	savingError: null,
	data: {},
	fetchingStatus: 'idle',
};

export const receiveSettings = (
	state = defaultState,
	{ type, ...action }
) => {
	switch ( type ) {
		case ACTION_TYPES.SET_SETTINGS:
			return {
				...state,
				data: action.data,
				fetchingStatus: 'resolved',
			};

		case ACTION_TYPES.SET_SETTINGS_FETCHING_STATUS:
			return {
				...state,
				fetchingStatus: action.status,
			};

		case ACTION_TYPES.SET_SETTINGS_VALUES:
			return {
				...state,
				savingError: null,
				data: {
					...state.data,
					...action.payload,
				},
			};

		case ACTION_TYPES.SET_IS_SAVING_SETTINGS:
			return {
				...state,
				isSaving: action.isSaving,
				savingError: action.error,
			};
	}

	return state;
};

export default receiveSettings;
