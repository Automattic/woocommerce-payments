/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useDeposit = ( id ) => useSelect( select => {
	const { getDeposit, isResolving } = select( STORE_NAME );

	return {
		deposit: getDeposit( id ),
		isLoading: isResolving( 'getDeposit', [ id ] ),
	};
}, [ id ] );

// eslint-disable-next-line camelcase
export const useDeposits = ( { paged, per_page: perPage } ) => useSelect( select => {
	const { getDeposits, getDepositQueryError, isResolving } = select( STORE_NAME );

	const query = {
		paged: paged != null ? paged : '1',
		perPage: perPage != null ? perPage : '25',
	};
	return {
		deposits: getDeposits( query ),
		depositsError: getDepositQueryError( query ),
		isLoading: isResolving( 'getDeposits', [ query ] ),
	};
}, [ paged, perPage ] );
