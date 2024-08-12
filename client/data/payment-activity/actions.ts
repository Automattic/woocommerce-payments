/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';
import type {
	PaymentActivityData,
	PaymentActivityAction,
	PaymentActivityQuery,
} from './types';

export function updatePaymentActivity(
	data: PaymentActivityData,
	query: PaymentActivityQuery
): PaymentActivityAction {
	return {
		type: TYPES.SET_PAYMENT_ACTIVITY_DATA,
		query,
		data,
	};
}
