/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

// eslint-disable-next-line camelcase
export const useDeposits = ( { paged = '1', per_page = '25' } ) => useSelect( select => {
	const { getDeposits, getDepositQueryError, isResolving } = select( STORE_NAME );

	// eslint-disable-next-line camelcase
	const query = { paged, perPage: per_page };
	return {
		deposits: getDeposits( query ),
		depositsError: getDepositQueryError( query ),
		isLoading: isResolving( 'getDeposits', [ query ] ),
	};
// eslint-disable-next-line camelcase
}, [ { paged, per_page } ] );
