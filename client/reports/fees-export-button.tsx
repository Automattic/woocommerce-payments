/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { select, useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { getQuery } from '@woocommerce/navigation';
import { recordEvent } from 'tracks';

/**
 * Internal dependencies
 */
import { useReportExport } from 'wcpay/hooks/use-report-export';
import { useReportsFeesSummary } from 'wcpay/data/reports/hooks';
import {
	feesDownloadEndpoint,
	getFeesCSVRequestURL,
} from 'wcpay/data/reports/resolvers';
import {
	buildFeesDateQueryFromUrlQuery,
	type FeesDateQueryParams,
} from './fees/date-filter-values';
import { store as reportsStore } from 'wcpay/data/reports';

/**
 * Threshold above which an unfiltered export prompts the merchant for
 * confirmation. Matches the Transactions list threshold — Fees rows are
 * 1:1 with transactions, so the same row count is the right peer.
 */
const confirmThreshold = 10000;

const hasValue = ( value: unknown ): boolean =>
	Array.isArray( value ) ? value.length > 0 : !! value;

interface FeesExportQuery extends FeesDateQueryParams {
	payment_method_type?: unknown;
	type?: unknown;
	order_id?: unknown;
	deposit_id?: unknown;
	search?: unknown;
	match?: unknown;
}

interface FeesSummaryStoreSelectors {
	getReportsFeesSummary: (
		query: Record< string, unknown >
	) => { count?: number } | undefined;
}

const buildFeesExportQuery = (
	query: Record< string, unknown >
): FeesExportQuery => ( {
	...buildFeesDateQueryFromUrlQuery( query ),
	payment_method_type: query.payment_method_type,
	type: query.type,
	order_id: query.order_id,
	deposit_id: query.deposit_id,
	search: query.search,
	match: query.match,
} );

const sortDateBetween = (
	dateBetween: FeesExportQuery[ 'date_between' ]
): FeesExportQuery[ 'date_between' ] =>
	Array.isArray( dateBetween )
		? [ ...dateBetween ].sort(
				( a, b ) => new Date( a ).getTime() - new Date( b ).getTime()
		  )
		: dateBetween;

const buildFeesSummaryStoreQuery = (
	query: FeesExportQuery
): Record< string, unknown > => ( {
	match: query.match,
	dateBefore: query.date_before,
	dateAfter: query.date_after,
	dateBetween: sortDateBetween( query.date_between ),
	paymentMethodType: query.payment_method_type,
	type: query.type,
	orderId: query.order_id,
	depositId: query.deposit_id,
	search: query.search,
} );

const getFeesSummaryCount = (
	query: FeesExportQuery,
	fallback: number
): number => {
	const summary = (
		select( reportsStore ) as unknown as FeesSummaryStoreSelectors
	 ).getReportsFeesSummary( buildFeesSummaryStoreQuery( query ) );

	return typeof summary?.count === 'number' ? summary.count : fallback;
};

/**
 * Triggers a Fees CSV export for the current URL-synced filter state.
 *
 * Reads filter state from `getQuery()` so the export can preserve
 * transaction-style datetime bounds in legacy/generated export URLs while the
 * on-screen DataViews table remains date-only. Delegates the async
 * POST → poll → download flow to `useReportExport` (shared with Transactions,
 * Payouts, and Disputes).
 */
export const FeesExportButton: React.FC = () => {
	const { requestReportExport, isExportInProgress } = useReportExport();
	const { createNotice } = useDispatch( 'core/notices' );
	const currentQuery = buildFeesExportQuery(
		getQuery() as Record< string, unknown >
	);
	const { feesSummary } = useReportsFeesSummary(
		currentQuery as Parameters< typeof useReportsFeesSummary >[ 0 ]
	);
	const totalRows = feesSummary?.count ?? 0;

	const onClick = () => {
		const userEmail = wcpaySettings.currentUserEmail;
		const locale = wcSettings.locale.userLocale;
		const query = buildFeesExportQuery(
			getQuery() as Record< string, unknown >
		);
		const exportTotalRows = getFeesSummaryCount( query, totalRows );

		const isFiltered =
			hasValue( query.date_after ) ||
			hasValue( query.date_before ) ||
			hasValue( query.date_between ) ||
			hasValue( query.payment_method_type ) ||
			hasValue( query.type ) ||
			hasValue( query.order_id ) ||
			hasValue( query.deposit_id ) ||
			hasValue( query.search );

		if (
			! isFiltered &&
			exportTotalRows >= confirmThreshold &&
			! window.confirm(
				sprintf(
					/* translators: %d: number of fees to be exported. */
					__(
						"You are about to export %d fees. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?",
						'woocommerce-payments'
					),
					exportTotalRows
				)
			)
		) {
			return;
		}

		recordEvent( 'wcpay_csv_export_click', {
			row_type: 'fees',
			source: 'payments_reports',
			exported_row_count: exportTotalRows,
		} );

		const exportRequestURL = getFeesCSVRequestURL( {
			userEmail,
			locale,
			dateAfter: query.date_after,
			dateBefore: query.date_before,
			dateBetween: query.date_between,
			paymentMethodType: query.payment_method_type,
			type: query.type,
			orderId: query.order_id,
			depositId: query.deposit_id,
			search: query.search,
			match: query.match,
		} );

		requestReportExport( {
			exportRequestURL,
			exportFileAvailabilityEndpoint: feesDownloadEndpoint,
			userEmail,
			onSuccess: () =>
				recordEvent( 'wcpay_reports_fees_export_success', {
					exported_row_count: exportTotalRows,
				} ),
			onError: ( { reason }: { reason: 'request' | 'timeout' } ) =>
				recordEvent( 'wcpay_reports_fees_export_error', {
					error_type: reason,
				} ),
		} );

		createNotice(
			'success',
			sprintf(
				/* translators: %s: the user's email address */
				__(
					'We’re processing your export. 🎉 The file will download automatically and be emailed to %s.',
					'woocommerce-payments'
				),
				userEmail
			)
		);
	};

	return (
		<Button
			variant="primary"
			onClick={ onClick }
			disabled={ isExportInProgress }
			accessibleWhenDisabled
			aria-busy={ isExportInProgress }
			isBusy={ isExportInProgress }
			__next40pxDefaultSize
		>
			{ __( 'Export', 'woocommerce-payments' ) }
		</Button>
	);
};
