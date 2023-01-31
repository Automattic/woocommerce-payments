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
	getRiskReviewListColumns,
	getRiskReviewListColumnsStructure,
} from './columns';

export const RiskReviewList = (): JSX.Element => {
	const query = getQuery();
	const columnsToDisplay = getRiskReviewListColumns();

	const {
		authorizationsSummary,
		isLoading: isSummaryLoading,
	} = useAuthorizationsSummary( query );

	const { authorizations, isLoading } = useAuthorizations( query );

	const rows = authorizations.map( ( auth ) =>
		getRiskReviewListColumnsStructure( auth, columnsToDisplay )
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
				label: __( 'transactions(s)', 'woocommerce-payments' ),
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
				label: __( 'pending', 'woocommerce-payments' ),
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
			path: 'payments_transactions_risk_review',
		} );
	}, [] );

	return (
		<Page>
			<TableCard
				className="authorizations-list woocommerce-report-table has-search"
				title={ __( 'Flagged transactions', 'woocommerce-payments' ) }
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

export default RiskReviewList;
