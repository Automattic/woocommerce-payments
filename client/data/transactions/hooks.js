/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from './constants';

export const useTransactionsPage = ( page ) => useSelect( select => {
	const { getTransactionsPage, isResolving } = select( STORE_NAME );
	return {
		transactions: getTransactionsPage( page ),
		loading: isResolving( 'getTransactionsPage', [ page ] ),
	};
}, [ page ] );
