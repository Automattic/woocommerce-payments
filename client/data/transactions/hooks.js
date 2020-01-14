/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useTransactions = () => useSelect( select => {
	const { getTransactions, isResolving } = select( STORE_NAME );
	return {
		transactions: getTransactions(),
		isLoading: isResolving( 'getTransactions' ),
	};
} );
