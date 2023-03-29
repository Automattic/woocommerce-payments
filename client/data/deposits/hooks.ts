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
import { Query } from '@woocommerce/navigation';
import {
	CachedDeposits,
	CachedDeposit,
	DepositsSummaryCache,
} from 'wcpay/types/deposits';
import { Transaction } from 'wcpay/data/transactions';

export const useDeposit = (
	id: string
): { deposit: CachedDeposit; isLoading: boolean } =>
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

export const useDepositIncludesLoan = (
	depositId?: string
): {
	transactions: Transaction[];
	includesFinancingPayout: boolean;
	includesFinancingPaydown: boolean;
	isLoading: boolean;
} => {
	const hasActiveLoan = wcpaySettings.accountLoans.has_active_loan;

	return useSelect(
		( select ) => {
			// Using a conditional select here to avoid fetching transactions if there is no active loan.
			if ( ! depositId || ! hasActiveLoan ) {
				return {
					transactions: [],
					includesFinancingPayout: false,
					includesFinancingPaydown: false,
					isLoading: false,
				};
			}

			const { getTransactions, isResolving } = select( STORE_NAME );
			const query: Query & {
				depositId: string;
			} = {
				depositId,
				page: '1',
				per_page: '100',
				orderby: 'date',
				order: 'desc',
			};
			const transactions = getTransactions( query ) as Transaction[];
			const isLoading = !! isResolving( 'getTransactions', [ query ] );

			const types = transactions.map( ( { type } ) => type );
			const includesFinancingPayout = types.includes(
				'financing_payout'
			);
			const includesFinancingPaydown = types.includes(
				'financing_paydown'
			);

			return {
				transactions,
				includesFinancingPayout,
				includesFinancingPaydown,
				isLoading,
			};
		},
		[ depositId, hasActiveLoan ]
	);
};

export const useDepositsOverview = (): {
	overviewError: unknown;
	isLoading: boolean;
	overview: unknown;
} =>
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
}: Query ): CachedDeposits =>
	useSelect(
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

export const useDepositsSummary = ( {
	match,
	store_currency_is: storeCurrencyIs,
	date_before: dateBefore,
	date_after: dateAfter,
	date_between: dateBetween,
	status_is: statusIs,
	status_is_not: statusIsNot,
}: Query ): DepositsSummaryCache =>
	useSelect(
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

export const useInstantDeposit = (
	transactionIds: string[]
): { inProgress: boolean; submit: () => void; deposit: unknown } => {
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
