/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import {
	PaymentIntentsActions,
	PaymentIntentsState,
	UpdateErrorForPaymentIntentAction,
	UpdatePaymentIntentAction,
} from './types';

const defaultState = {};

const receivePaymentIntents = (
	state: PaymentIntentsState = defaultState,
	action: PaymentIntentsActions
): PaymentIntentsState => {
	const { type, id } = action;

	switch ( type ) {
		case TYPES.SET_PAYMENT_INTENT:
			return {
				...state,
				[ id ]: {
					...state[ id ],
					data: ( action as UpdatePaymentIntentAction ).data,
				},
			};
		case TYPES.SET_ERROR_FOR_PAYMENT_INTENT:
			return {
				...state,
				[ id ]: {
					...state[ id ],
					error: ( action as UpdateErrorForPaymentIntentAction )
						.error,
				},
			};
		default:
			return state;
	}
};

export default receivePaymentIntents;
