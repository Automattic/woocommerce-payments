/** @format */

/**
 * Internal Dependencies
 */
import { State } from 'wcpay/data/types';
import { PaymentActivityData } from './types';

export const getPaymentActivityData = (
	state: State
): PaymentActivityData | undefined => {
	return state?.paymentActivity?.paymentActivityData;
};
