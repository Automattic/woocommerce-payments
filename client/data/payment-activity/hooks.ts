/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';

// eslint-disable-next-line @typescript-eslint/explicit-module-boundary-types
export const usePaymentActivityData = ( query: any ) =>
	useSelect( ( select ) => {
		const { getPaymentActivityData, isResolving } = select( STORE_NAME );
		const x = getPaymentActivityData( query );
		return {
			paymentActivityData: x,
			isLoading: isResolving( 'getPaymentActivityData', [] ),
		};
	}, [] );
