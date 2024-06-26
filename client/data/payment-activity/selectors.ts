/** @format */

/**
 * Internal Dependencies
 */
import type { State } from 'wcpay/data/types';
import type { PaymentActivityData, PaymentActivityQuery } from './types';
import { getResourceId } from 'wcpay/utils/data';

export const getPaymentActivityData = (
	state: State,
	query: PaymentActivityQuery
): PaymentActivityData | undefined => {
	const index = getResourceId( query );
	return state?.paymentActivity?.[ index ];
};
