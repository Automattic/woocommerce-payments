/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';

export function updatePaymentActivity( data ) {
	return {
		type: TYPES.SET_PAYMENT_ACTIVITY_DATA,
		data,
	};
}
