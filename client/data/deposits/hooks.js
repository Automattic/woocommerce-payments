/** @format */

/**
 * External dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useDeposit = ( id ) =>
	useSelect(
		( select ) => {
			const { getDeposit, isResolving } = select( STORE_NAME );

			return {
				deposit: getDeposit( id ),
				isLoading: isResolving( 'getDeposit', [ id ] ),
			};
		},
		[ id ]
	);

export const useDepositsOverview = () =>
	useSelect( ( select ) => {
		const {
			getDepositsOverview,
			getDepositsOverviewError,
			isResolving,
		} = select( STORE_NAME );

		return {
			overview: getDepositsOverview(),
			overviewError: getDepositsOverviewError(),
			isLoading: isResolving( 'getDepositsOverview' ),
		};
	} );

// eslint-disable-next-line camelcase
export const useDeposits = ( { paged, per_page: perPage } ) =>
	useSelect(
		( select ) => {
			const { getDeposits, getDepositQueryError, isResolving } = select(
				STORE_NAME
			);

			const query = {
				paged: Number.isNaN( parseInt( paged, 10 ) ) ? '1' : paged,
				perPage: Number.isNaN( parseInt( perPage, 10 ) )
					? '25'
					: perPage,
			};
			return {
				deposits: getDeposits( query ),
				depositsError: getDepositQueryError( query ),
				isLoading: isResolving( 'getDeposits', [ query ] ),
			};
		},
		[ paged, perPage ]
	);

export const useInstantDeposit = ( transaction_ids ) => {
	const { deposit, inProgress } = useSelect(
		( select ) => {
			const { getInstantDeposit, isResolving } = select( STORE_NAME );

			return {
				deposit: getInstantDeposit( [ transaction_ids ] ),
				inProgress: isResolving( 'getInstantDeposit', [
					transaction_ids,
				] ),
			};
		},
		[ transaction_ids ]
	);
	const { submitInstantDeposit } = useDispatch( STORE_NAME );
	const submit = () => submitInstantDeposit( transaction_ids );

	console.log( 'deposit' );
	console.log( deposit );

	return { deposit, inProgress, submit };
};

export const useDepositsPage = () => {
	const { needsReload } = useSelect( ( select ) => {
		const { getDepositsPage } = select( STORE_NAME );

		return {
			needsReload: getDepositsPage(),
		};
	} );

	return { needsReload };
};
