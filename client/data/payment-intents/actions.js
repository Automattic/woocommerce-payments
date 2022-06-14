/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updatePaymentIntent( id, data ) {
	return {
		type: TYPES.SET_PAYMENT_INTENT,
		id,
		data,
	};
}

export function updateErrorForPaymentIntent( id, data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_PAYMENT_INTENT,
		id,
		data,
		error,
	};
}
