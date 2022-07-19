/** @format */

/**
 * Internal Dependencies
 */
import { ApiError } from '../../types/errors';
import { PaymentIntent } from '../../types/payment-intents';
import TYPES from './action-types';
import {
	UpdateErrorForPaymentIntentAction,
	UpdatePaymentIntentAction,
} from './types';

export function updatePaymentIntent(
	id: string,
	data: PaymentIntent
): UpdatePaymentIntentAction {
	return {
		type: TYPES.SET_PAYMENT_INTENT,
		id,
		data,
	};
}

export function updateErrorForPaymentIntent(
	id: string,
	error: ApiError
): UpdateErrorForPaymentIntentAction {
	return {
		type: TYPES.SET_ERROR_FOR_PAYMENT_INTENT,
		id,
		error,
	};
}
