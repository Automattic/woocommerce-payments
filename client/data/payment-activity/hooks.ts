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

		return {
			paymentActivityData: getPaymentActivityData( query ),
			isLoading: isResolving( 'getPaymentActivityData', [] ),
		};
	}, [] );
