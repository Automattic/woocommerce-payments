/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';
import { PaymentActivityData, PaymentsActivityAction } from './types';

export function updatePaymentsActivity(
	data: PaymentActivityData
): PaymentsActivityAction {
	return {
		type: TYPES.SET_PAYMENT_ACTIVITY_DATA,
		data,
	};
}
