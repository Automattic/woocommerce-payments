/**
 * Internal dependencies
 */
import { Charge } from '../types/charges';
import { PaymentIntent } from '../types/payment-intents';
import { ApiError } from 'wcpay/types/errors';

export interface PaymentChargeDetails {
	data: PaymentIntent | Charge;
	error: ApiError;
	isLoading: boolean;
}

export interface PaymentDetailsProps {
	query: {
		id: string;
		transaction_id?: string;
		transaction_type?: string;
	};
}

export interface PaymentChargeDetailsProps {
	id: string;
}

export const isPaymentIntent = (
	data: PaymentIntent | Charge
): data is PaymentIntent => {
	return ( data as PaymentIntent ).charge !== undefined;
};

export const isCharge = ( data: PaymentIntent | Charge ): data is Charge => {
	return ( data as PaymentIntent ).charge === undefined;
};
