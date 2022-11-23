/** @format */

/**
 * Internal dependencies
 */
import { ApiError } from '../../types/errors';
import { PaymentIntent } from '../../types/payment-intents';
import { State } from '../types';

export const getPaymentIntent = (
	{ paymentIntents }: State,
	id: string
): PaymentIntent => {
	const paymentIntent = paymentIntents?.[ id ];

	return paymentIntent?.data || ( {} as PaymentIntent );
};

export const getPaymentIntentError = (
	{ paymentIntents }: State,
	id: string
): ApiError => {
	const paymentIntent = paymentIntents?.[ id ];

	return paymentIntent?.error || ( {} as ApiError );
};
