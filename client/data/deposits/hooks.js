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

export const useAllDeposistsOverviews = () =>
	useSelect( ( select ) => {
		const {
			getAllDepositsOverviews,
			getAllDepositsOverviewsError,
			isResolving,
		} = select( STORE_NAME );

		return {
			overviews: getAllDepositsOverviews(),
			overviewError: getAllDepositsOverviewsError(),
			isLoading: isResolving( 'getAllDepositsOverviews' ),
		};
	} );

// eslint-disable-next-line camelcase
export const useDeposits = ( {
	paged,
	per_page: perPage,
	orderby = 'date',
	order = 'desc',
	store_currency_is: storeCurrencyIs,
	match,
	date_before: dateBefore,
	date_after: dateAfter,
	date_between: dateBetween,
} ) =>
	useSelect(
		( select ) => {
			const {
				getDeposits,
				getDepositsCount,
				getDepositQueryError,
				isResolving,
			} = select( STORE_NAME );

			const query = {
				paged: Number.isNaN( parseInt( paged, 10 ) ) ? '1' : paged,
				perPage: Number.isNaN( parseInt( perPage, 10 ) )
					? '25'
					: perPage,
				orderby,
				order,
				storeCurrencyIs,
				match,
				dateBefore,
				dateAfter,
				dateBetween,
			};
			return {
				deposits: getDeposits( query ),
				depositsCount: getDepositsCount(),
				depositsError: getDepositQueryError( query ),
				isLoading: isResolving( 'getDeposits', [ query ] ),
			};
		},
		[
			paged,
			perPage,
			orderby,
			order,
			storeCurrencyIs,
			match,
			dateBefore,
			dateAfter,
			dateBetween,
		]
	);

export const useDepositsSummary = ( {
	match,
	store_currency_is: storeCurrencyIs,
	date_before: dateBefore,
	date_after: dateAfter,
	date_between: dateBetween,
} ) =>
	useSelect(
		( select ) => {
			const { getDepositsSummary, isResolving } = select( STORE_NAME );

			const query = {
				match,
				storeCurrencyIs,
				dateBefore,
				dateAfter,
				dateBetween,
			};

			return {
				depositsSummary: getDepositsSummary( query ),
				isLoading: isResolving( 'getDepositsSummary', [ query ] ),
			};
		},
		[ storeCurrencyIs, match, dateBefore, dateAfter, dateBetween ]
	);

export const useInstantDeposit = ( transactionIds ) => {
	const { deposit, inProgress } = useSelect( ( select ) => {
		const { getInstantDeposit, isResolving } = select( STORE_NAME );

		return {
			deposit: getInstantDeposit( [ transactionIds ] ),
			inProgress: isResolving( 'getInstantDeposit', [ transactionIds ] ),
		};
	} );
	const { submitInstantDeposit } = useDispatch( STORE_NAME );
	const submit = () => submitInstantDeposit( transactionIds );

	return { deposit, inProgress, submit };
};
