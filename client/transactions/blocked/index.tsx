/** @format **/

/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Search, TableCard } from '@woocommerce/components';
import {
	onQueryChange,
	getQuery,
	updateQueryString,
} from '@woocommerce/navigation';
import { uniq } from 'lodash';

/**
 * Internal dependencies
 */
import {
	useFraudOutcomeTransactions,
	useFraudOutcomeTransactionsSummary,
} from 'data/index';
import Page from '../../components/page';
import wcpayTracks from 'tracks';
import {
	getBlockedListColumns,
	getBlockedListColumnsStructure,
} from './columns';
import { formatExplicitCurrency } from '../../utils/currency';
import autocompleter from '../fraud-protection/autocompleter';

export const BlockedList = (): JSX.Element => {
	const query = getQuery();

	const columnsToDisplay = getBlockedListColumns();
	const { isLoading, transactions } = useFraudOutcomeTransactions(
		'block',
		query
	);

	const {
		transactionsSummary,
		isLoading: isSummaryLoading,
	} = useFraudOutcomeTransactionsSummary( 'block', query );

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
					transactionsSummary.total as number,
					transactionsSummary.currencies[ 0 ]
				) }`,
			} );
		}
	}

	useEffect( () => {
		wcpayTracks.recordEvent( 'page_view', {
			path: 'payments_transactions_blocked',
		} );
	}, [] );

	const searchedLabels =
		getQuery().search &&
		getQuery().search?.map( ( v ) => ( {
			key: v,
			label: v,
		} ) );

	const onSearchChange = ( values: { key: string; label: string }[] ) => {
		updateQueryString( {
			search: values.length
				? uniq( values.map( ( v ) => v.key || v.label ) )
				: undefined,
		} );
	};

	const searchPlaceholder = __(
		'Search by order number or customer name',
		'woocommerce-payments'
	);

	return (
		<Page>
			<TableCard
				className="authorizations-list woocommerce-report-table has-search"
				title={ __( 'Blocked transactions', 'woocommerce-payments' ) }
				isLoading={ isLoading }
				rowsPerPage={ parseInt( query.per_page ?? '', 10 ) || 25 }
				totalRows={ totalRows }
				headers={ columnsToDisplay }
				rows={ rows }
				summary={ summary }
				query={ query }
				onQueryChange={ onQueryChange }
				actions={ [
					<Search
						inlineTags
						key="search"
						onChange={ onSearchChange }
						placeholder={ searchPlaceholder }
						selected={ searchedLabels }
						showClearButton={ true }
						type={
							wcpaySettings.featureFlags.customSearch
								? 'custom'
								: 'customers'
						}
						autocompleter={ autocompleter( 'block' ) }
					/>,
				] }
			/>
		</Page>
	);
};

export default BlockedList;
