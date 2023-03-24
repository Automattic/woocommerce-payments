/** @format */

/**
 * Internal Dependencies
 */
import { Charge } from '../../types/charges';
import { PaymentIntent } from '../../types/payment-intents';
import { ApiError } from '../../types/errors';

export interface ChargeResponse {
	data: Charge;
	error: ApiError;
	isLoading: boolean;
}

export interface PaymentIntentResponse {
	data: PaymentIntent;
	error: ApiError;
	isLoading: boolean;
}
