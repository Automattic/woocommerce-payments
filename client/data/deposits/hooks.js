/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
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

export const useDepositsOverview = ( deposit ) =>
	useSelect(
		( select ) => {
			const {
				getDepositsOverview,
				getDepositsOverviewError,
				isResolving,
			} = select( STORE_NAME );

			return {
				overview: getDepositsOverview( deposit ),
				overviewError: getDepositsOverviewError(),
				isLoading: isResolving( 'getDepositsOverview', [ deposit ] ),
			};
		},
		[ deposit ]
	);

// eslint-disable-next-line camelcase
export const useDeposits = ( { paged, per_page: perPage }, deposit ) =>
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
				deposits: getDeposits( query, deposit ),
				depositsError: getDepositQueryError( query ),
				isLoading: isResolving( 'getDeposits', [ query, deposit ] ),
			};
		},
		[ paged, perPage, deposit ]
	);

export const useInstantDeposit = ( transaction_ids ) => {
	const { deposit, inProgress } = useSelect( ( select ) => {
		const { getInstantDeposit, isResolving } = select( STORE_NAME );

		return {
			deposit: getInstantDeposit( [ transaction_ids ] ),
			inProgress: isResolving( 'getInstantDeposit', [ transaction_ids ] ),
		};
	} );
	const { submitInstantDeposit } = useDispatch( STORE_NAME );
	const submit = () => submitInstantDeposit( transaction_ids );

	return { deposit, inProgress, submit };
};
