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
import { useAuthorizations, useAuthorizationsSummary } from 'data/index';
import Page from '../../components/page';
import { formatExplicitCurrency } from 'utils/currency';
import wcpayTracks from 'tracks';
import {
	getBlockedListColumns,
	getBlockedListColumnsStructure,
} from './columns';

export const BlockedList = (): JSX.Element => {
	const columnsToDisplay = getBlockedListColumns();
	const {
		authorizationsSummary,
		isLoading: isSummaryLoading,
	} = useAuthorizationsSummary( getQuery() );

	const { authorizations, isLoading } = useAuthorizations( getQuery() );

	const rows = authorizations.map( ( auth ) =>
		getBlockedListColumnsStructure( auth, columnsToDisplay )
	);

	let summary;

	const isAuthorizationsSummaryLoaded =
		authorizationsSummary.count !== undefined &&
		authorizationsSummary.total !== undefined &&
		false === isSummaryLoading;
	const totalRows = authorizationsSummary.count || 0;

	if ( isAuthorizationsSummaryLoaded ) {
		summary = [
			{
				label: __( 'authorization(s)', 'woocommerce-payments' ),
				value: String( authorizationsSummary.count ),
			},
		];

		if (
			authorizationsSummary.count &&
			authorizationsSummary.count > 0 &&
			authorizationsSummary.all_currencies &&
			authorizationsSummary.all_currencies.length === 1
		) {
			// Only show the total if there is one currency available
			summary.push( {
				label: __( 'total', 'woocommerce-payments' ),
				value: `${ formatExplicitCurrency(
					// We've already checked that `.total` is not undefined, but TypeScript doesn't detect
					// that so we remove the `undefined` in the type manually.
					authorizationsSummary.total as number,
					authorizationsSummary.currency
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
				isLoading={ isLoading || isSummaryLoading }
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
