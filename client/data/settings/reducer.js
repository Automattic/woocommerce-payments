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

		case TYPES.SET_IS_DIGITAL_WALLETS_ENABLED:
			return {
				...state,
				data: {
					...state.data,
					// eslint-disable-next-line camelcase
					is_digital_wallets_enabled: action.isEnabled,
				},
			};

		case TYPES.SET_DIGITAL_WALLETS_SECTIONS:
			return {
				...state,
				data: {
					...state.data,
					// eslint-disable-next-line camelcase
					digital_wallets_enabled_sections: {
						...action.digital_wallets_enabled_sections,
					},
				},
			};
	}

	return state;
};

export default receiveSettings;
