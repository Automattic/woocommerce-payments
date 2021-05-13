/** @format */

/**
 * Internal dependencies
 */
import ACTION_TYPES from './action-types';

const defaultState = {
	isSaving: false,
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
			};

		case ACTION_TYPES.SET_IS_SAVING_SETTINGS:
			return {
				...state,
				isSaving: action.isSaving,
			};

		case ACTION_TYPES.SET_ENABLED_PAYMENT_METHOD_IDS:
			return {
				...state,
				data: {
					...state.data,
					// eslint-disable-next-line camelcase
					enabled_payment_method_ids: action.methodIds,
				},
			};

		case ACTION_TYPES.SET_IS_WCPAY_ENABLED:
			return {
				...state,
				data: {
					...state.data,
					// eslint-disable-next-line camelcase
					is_wcpay_enabled: action.isEnabled,
				},
			};
	}

	return state;
};

export default receiveSettings;
