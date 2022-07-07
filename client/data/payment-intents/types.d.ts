/** @format */

/**
 * Internal Dependencies
 */
import { Charge } from '../../types/charges';
import { ApiError } from '../../types/errors';
import { PaymentIntent } from '../../types/payment-intents';
import ACTION_TYPES from './action-types';

export interface UpdatePaymentIntentAction {
	type: ACTION_TYPES.SET_PAYMENT_INTENT;
	id: string;
	data: PaymentIntent;
}

export interface UpdateErrorForPaymentIntentAction {
	type: ACTION_TYPES.SET_ERROR_FOR_PAYMENT_INTENT;
	id: string;
	error: ApiError;
}

export interface PaymentIntentsState {
	[ key: string ]: {
		id: string;
		data?: PaymentIntent;
		error?: ApiError;
	};
}

export type PaymentIntentsActions =
	| UpdatePaymentIntentAction
	| UpdateErrorForPaymentIntentAction;

export interface PaymentIntentionFallbackResponse {
	data: Charge;
	error: ApiError;
	isLoading: boolean;
	redirect?: {
		url: string;
	};
}
