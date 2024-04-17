/** @format */

/**
 * Internal Dependencies
 */
import TYPES from './action-types';
import { PaymentActivityData, PaymentActivityAction } from './types';

export function updatePaymentActivity(
	data: PaymentActivityData
): PaymentActivityAction {
	return {
		type: TYPES.SET_PAYMENT_ACTIVITY_DATA,
		data,
	};
}
