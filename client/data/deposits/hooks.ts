/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import moment from 'moment';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';
import type { Query } from '@woocommerce/navigation';
import type {
	CachedDeposits,
	CachedDeposit,
	DepositsSummaryCache,
} from 'wcpay/types/deposits';
import type { Transaction } from 'wcpay/data/transactions';
import type * as AccountOverview from 'wcpay/types/account-overview';

export const useDeposit = (
	id: string
): { deposit: CachedDeposit; isLoading: boolean } =>
	useSelect(
		( select ) => {
			const { getDeposit, isResolving, hasFinishedResolution } = select(
				STORE_NAME
			);

			return {
				deposit: getDeposit( id ),
				isLoading:
					! hasFinishedResolution( 'getDeposit', [ id ] ) ||
					isResolving( 'getDeposit', [ id ] ),
			};
		},
		[ id ]
	);

export const useDepositIncludesLoan = (
	depositId?: string
): {
	includesFinancingPayout: boolean;
	isLoading: boolean;
} => {
	const hasActiveLoan = wcpaySettings.accountLoans.has_active_loan;

	return useSelect(
		( select ) => {
			// Using a conditional select here to avoid fetching transactions if there is no active loan.
			if ( ! depositId || ! hasActiveLoan ) {
				return {
					includesFinancingPayout: false,
					isLoading: false,
				};
			}

			const { getTransactions, isResolving } = select( STORE_NAME );
			const query = {
				depositId,
				page: '1',
				typeIs: 'financing_payout',
				perPage: '1',
				orderby: 'date',
				order: 'desc',
			};
			const financingPayoutTransactions = getTransactions(
				query
			) as Transaction[];
			const isLoading = !! isResolving( 'getTransactions', [ query ] );

			return {
				includesFinancingPayout: financingPayoutTransactions.length > 0,
				isLoading,
			};
		},
		[ depositId, hasActiveLoan ]
	);
};

export const useAllDepositsOverviews = (): AccountOverview.OverviewsResponse =>
	useSelect( ( select ) => {
		const {
			getAllDepositsOverviews,
			getAllDepositsOverviewsError,
			isResolving,
			hasFinishedResolution,
		} = select( STORE_NAME );

		return {
			overviews: getAllDepositsOverviews(),
			overviewError: getAllDepositsOverviewsError(),
			isLoading:
				! hasFinishedResolution( 'getAllDepositsOverviews' ) ||
				isResolving( 'getAllDepositsOverviews' ),
		};
	} );

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
	status_is: statusIs,
	status_is_not: statusIsNot,
}: Query ): CachedDeposits => {
	return useSelect(
		( select ) => {
			const {
				getDeposits,
				getDepositsCount,
				getDepositQueryError,
				isResolving,
			} = select( STORE_NAME );

			const query = {
				paged: Number.isNaN( parseInt( paged ?? '', 10 ) )
					? '1'
					: paged,
				perPage: Number.isNaN( parseInt( perPage ?? '', 10 ) )
					? '25'
					: perPage,
				orderby,
				order,
				storeCurrencyIs,
				match,
				dateBefore,
				dateAfter,
				dateBetween:
					dateBetween &&
					dateBetween.sort( ( a, b ) =>
						moment( a ).diff( moment( b ) )
					),
				statusIs,
				statusIsNot,
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
			JSON.stringify( dateBetween ),
			statusIs,
			statusIsNot,
		]
	);
};

export const useDepositsSummary = ( {
	match,
	store_currency_is: storeCurrencyIs,
	date_before: dateBefore,
	date_after: dateAfter,
	date_between: dateBetween,
	status_is: statusIs,
	status_is_not: statusIsNot,
}: Query ): DepositsSummaryCache => {
	return useSelect(
		( select ) => {
			const { getDepositsSummary, isResolving } = select( STORE_NAME );

			const query = {
				match,
				storeCurrencyIs,
				dateBefore,
				dateAfter,
				dateBetween,
				statusIs,
				statusIsNot,
			};

			return {
				depositsSummary: getDepositsSummary( query ),
				isLoading: isResolving( 'getDepositsSummary', [ query ] ),
			};
		},
		[
			storeCurrencyIs,
			match,
			dateBefore,
			dateAfter,
			JSON.stringify( dateBetween ),
			statusIs,
			statusIsNot,
		]
	);
};

export const useInstantDeposit = (
	currency: string
): { inProgress: boolean; submit: () => void; deposit: unknown } => {
	const { deposit, inProgress } = useSelect( ( select ) => {
		const { getInstantDeposit, isResolving } = select( STORE_NAME );

		return {
			deposit: getInstantDeposit( [ currency ] ),
			inProgress: isResolving( 'getInstantDeposit', [ currency ] ),
		};
	} );
	const { submitInstantDeposit } = useDispatch( STORE_NAME );
	const submit = () => submitInstantDeposit( currency );

	return { deposit, inProgress, submit };
};
