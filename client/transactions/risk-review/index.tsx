/** @format **/

/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { TableCard } from '@woocommerce/components';
import { onQueryChange, getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import {
	useOnReviewTransactions,
	useOnReviewTransactionsSummary,
} from 'data/index';
import Page from '../../components/page';
import wcpayTracks from 'tracks';
import {
	getRiskReviewListColumns,
	getRiskReviewListColumnsStructure,
} from './columns';
import './style.scss';
import { formatExplicitCurrency } from '../../utils/currency';

export const RiskReviewList = (): JSX.Element => {
	const query = getQuery();
	const columnsToDisplay = getRiskReviewListColumns();

	const {
		transactions,
		transactionsError,
		isLoading,
	} = useOnReviewTransactions( query );

	const {
		transactionsSummary,
		isLoading: isSummaryLoading,
	} = useOnReviewTransactionsSummary( query );

	// const { authorizations, isLoading } = useAuthorizations( query );

	const rows = transactions.map( ( transaction ) =>
		getRiskReviewListColumnsStructure( transaction, columnsToDisplay )
	);

	let summary;

	const isAuthorizationsSummaryLoaded =
		transactionsSummary.count !== undefined &&
		transactionsSummary.total !== undefined &&
		false === isSummaryLoading;
	const totalRows = transactionsSummary.count || 0;

	if ( isAuthorizationsSummaryLoaded ) {
		summary = [
			{
				label: __( 'transactions(s)', 'woocommerce-payments' ),
				value: String( totalRows ),
			},
		];

		if ( totalRows > 0 && transactionsSummary.currencies?.length === 1 ) {
			// Only show the total if there is one currency available
			summary.push( {
				label: __( 'pending', 'woocommerce-payments' ),
				value: `${ formatExplicitCurrency(
					transactionsSummary.total,
					transactionsSummary.currencies[ 0 ]
				) }`,
			} );
		}
	}

	useEffect( () => {
		wcpayTracks.recordEvent( 'page_view', {
			path: 'payments_transactions_risk_review',
		} );
	}, [] );

	return (
		<Page>
			<TableCard
				className="authorizations-list woocommerce-report-table has-search"
				title={ __( 'Flagged transactions', 'woocommerce-payments' ) }
				isLoading={ isLoading }
				rowsPerPage={ parseInt( getQuery().per_page ?? '', 10 ) || 25 }
				totalRows={ totalRows }
				headers={ columnsToDisplay }
				rows={ rows }
				summary={ summary }
				query={ getQuery() }
				onQueryChange={ onQueryChange }
			/>
		</Page>
	);
};

export default RiskReviewList;
