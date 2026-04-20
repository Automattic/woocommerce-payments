/**
 * External dependencies
 */
import { useMemo } from 'react';

/**
 * Internal dependencies
 */
import { TimelineItem } from 'wcpay/data/timeline/types';
import { formatCurrency } from 'multi-currency/interface/functions';

interface TransactionAmounts {
	formattedStoreAmount: string;
	formattedCustomerAmount: string;
	isMultiCurrency: boolean;
	formattedAmount: string;
}

export const useTransactionAmounts = (
	captureEvent: TimelineItem | undefined
): TransactionAmounts | null => {
	return useMemo( () => {
		if ( undefined === captureEvent ) {
			return null;
		}

		const { transaction_details: details } = captureEvent;
		if ( ! details ) return null;

		const formattedStoreAmount = `${ formatCurrency(
			details.store_amount,
			details.store_currency
		) } ${ details.store_currency }`;

		const formattedCustomerAmount = `${ formatCurrency(
			details.customer_amount,
			details.customer_currency,
			details.store_currency
		) } ${ details.customer_currency }`;

		const isMultiCurrency =
			details.store_currency !== details.customer_currency;

		return {
			formattedStoreAmount,
			formattedCustomerAmount,
			isMultiCurrency,
			formattedAmount: `${ formattedCustomerAmount }${
				isMultiCurrency ? ` → ${ formattedStoreAmount }` : ''
			}`,
		};
	}, [ captureEvent ] );
};
