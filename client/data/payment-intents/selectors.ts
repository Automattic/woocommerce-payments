/** @format */

/**
 * Internal dependencies
 */
import { ApiError } from '../../types/errors';
import { PaymentIntent } from '../../types/payment-intents';
import { State } from '../types';

const emptyPaymentIntent = {} as PaymentIntent;
const emptyError = {} as ApiError;

export const getPaymentIntent = (
	{ paymentIntents }: State,
	id: string
): PaymentIntent => {
	const paymentIntent = paymentIntents?.[ id ];

	return paymentIntent?.data || emptyPaymentIntent;
};

export const getPaymentIntentError = (
	{ paymentIntents }: State,
	id: string
): ApiError => {
	const paymentIntent = paymentIntents?.[ id ];

	return paymentIntent?.error || emptyError;
};
