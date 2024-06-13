/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';
import { PaymentActivityData, PaymentActivityQuery } from './types';

// eslint-disable-next-line @typescript-eslint/explicit-module-boundary-types
export const usePaymentActivityData = (
	query: PaymentActivityQuery
): {
	paymentActivityData: PaymentActivityData | undefined;
	isLoading: boolean;
} =>
	useSelect(
		( select ) => {
			const { getPaymentActivityData, isResolving } = select(
				STORE_NAME
			);

			return {
				paymentActivityData: getPaymentActivityData( query ),
				isLoading: isResolving( 'getPaymentActivityData', [ query ] ),
			};
		},
		[ query.currency, query.date_start, query.date_end ]
	);
