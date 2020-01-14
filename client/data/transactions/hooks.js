/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from './constants';

export const useTransactionsForPage = ( page ) => useSelect( select => {
	const { getTransactionsForPage, isResolving } = select( STORE_NAME );
	return {
		transactions: getTransactionsForPage( page ),
		isLoading: isResolving( 'getTransactionsForPage', [ page ] ),
	};
}, [ page ] );
