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
	useBlockedTransactions,
	useBlockedTransactionsSummary,
} from 'data/index';
import Page from '../../components/page';
import wcpayTracks from 'tracks';
import {
	getBlockedListColumns,
	getBlockedListColumnsStructure,
} from './columns';
import { formatExplicitCurrency } from '../../utils/currency';

export const BlockedList = (): JSX.Element => {
	const query = getQuery();

	const columnsToDisplay = getBlockedListColumns();
	const {
		isLoading,
		transactions,
		transactionsError,
	} = useBlockedTransactions( query );

	const {
		transactionsSummary,
		isLoading: isSummaryLoading,
	} = useBlockedTransactionsSummary( query );

	const rows = transactions.map( ( transaction ) =>
		getBlockedListColumnsStructure( transaction, columnsToDisplay )
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
				label: __( 'blocked', 'woocommerce-payments' ),
				value: `${ formatExplicitCurrency(
					transactionsSummary.total,
					transactionsSummary.currency
				) }`,
			} );
		}
	}

	useEffect( () => {
		wcpayTracks.recordEvent( 'page_view', {
			path: 'payments_transactions_blocked',
		} );
	}, [] );

	return (
		<Page>
			<TableCard
				className="authorizations-list woocommerce-report-table has-search"
				title={ __( 'Blocked transactions', 'woocommerce-payments' ) }
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

export default BlockedList;
