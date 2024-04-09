/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updatePaymentActivity( query, data ) {
	return {
		type: TYPES.SET_PAYMENT_ACTIVITY_DATA,
		query,
		data,
	};
}
