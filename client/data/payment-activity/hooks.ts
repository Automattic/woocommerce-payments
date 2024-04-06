/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';

export const usePaymentActivityData = () =>
	useSelect( ( select ) => {
		const { getPaymentActivityData, isResolving } = select( STORE_NAME );

		return {
			data: getPaymentActivityData(),
			isLoading: isResolving( 'getPaymentActivityData', [] ),
		};
	}, [] );