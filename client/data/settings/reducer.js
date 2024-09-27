/** @format */

/**
 * Internal dependencies
 */
import ACTION_TYPES from './action-types';

const defaultState = {
	isDirty: false,
	isSaving: false,
	savingError: null,
	data: {},
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
				isDirty: false,
			};

		case ACTION_TYPES.SET_SETTINGS_VALUES:
			return {
				...state,
				savingError: null,
				isDirty: true,
				data: {
					...state.data,
					...action.payload,
				},
			};

		case ACTION_TYPES.SET_IS_SAVING_SETTINGS:
			return {
				...state,
				isDirty:
					action.isSaving || action.error ? state.isDirty : false,
				isSaving: action.isSaving,
				savingError: action.error,
			};

		case ACTION_TYPES.SET_SELECTED_PAYMENT_METHOD:
			return {
				...state,
				isDirty: true,
				data: {
					...state.data,
					enabled_payment_method_ids: state.data.enabled_payment_method_ids.concat(
						action.id
					),
				},
			};

		case ACTION_TYPES.SET_UNSELECTED_PAYMENT_METHOD:
			return {
				...state,
				isDirty: true,
				data: {
					...state.data,
					enabled_payment_method_ids: state.data.enabled_payment_method_ids.filter(
						( id ) => id !== action.id
					),
				},
			};
	}

	return state;
};

export default receiveSettings;
