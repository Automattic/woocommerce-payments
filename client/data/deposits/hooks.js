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

export const useDepositsOverview = () => useSelect( select => {
	const { getDepositsOverview, getDepositsOverviewError, isResolving } = select( STORE_NAME );

	return {
		overview: getDepositsOverview(),
		overviewError: getDepositsOverviewError(),
		isLoading: isResolving( 'getDepositsOverview' ),
	};
} );

// eslint-disable-next-line camelcase
export const useDeposits = ( { paged, per_page: perPage } ) => useSelect( select => {
	const { getDeposits, getDepositQueryError, isResolving } = select( STORE_NAME );

	const query = {
		paged: Number.isNaN( parseInt( paged, 10 ) ) ? '1' : paged,
		perPage: Number.isNaN( parseInt( perPage, 10 ) ) ? '25' : perPage,
	};
	return {
		deposits: getDeposits( query ),
		depositsError: getDepositQueryError( query ),
		isLoading: isResolving( 'getDeposits', [ query ] ),
	};
}, [ paged, perPage ] );
