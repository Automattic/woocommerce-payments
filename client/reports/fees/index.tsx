/** @format */

/**
 * External dependencies
 */
import React, { useCallback, useEffect, useMemo, useRef } from 'react';
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { DataViews } from '@wordpress/dataviews';

/**
 * Internal dependencies
 */
import DownloadButton from 'wcpay/components/download-button';
import { useReportExport } from 'wcpay/hooks/use-report-export';
import {
	getReportsFeesCSVRequestURL,
	reportsFeesDownloadEndpoint,
} from 'wcpay/data/reports/resolvers';
import { recordEvent } from 'tracks';
import { useFeesView } from './use-fees-view';
import { useFeesData } from './use-fees-data';
import { getFeesFields } from './fields';
import type { ReportsPeriodRange } from '../period-selector';
import type { View, Filter } from '@wordpress/dataviews';

interface FeesReportProps {
	period: ReportsPeriodRange;
	onReload?: () => void;
}

/**
 * Returns true when the user's visible-fields configuration has changed.
 * Compared as joined strings — the field list is small and order-significant.
 */
const haveFieldsChanged = (
	prev: ReadonlyArray< string > = [],
	next: ReadonlyArray< string > = []
): boolean => prev.join( '|' ) !== next.join( '|' );

const findDateFilter = ( filters: Filter[] = [] ): Filter | undefined =>
	filters.find( ( f ) => f.field === 'date' );

/**
 * Returns true when the date filter (operator + value) differs between two
 * filter arrays. Comparing the date filter only — not the entire `filters`
 * array — keeps the comparison stable when unrelated filters change.
 */
const haveDateFiltersChanged = (
	prev: Filter[] = [],
	next: Filter[] = []
): boolean => {
	const a = findDateFilter( prev );
	const b = findDateFilter( next );
	if ( ! a && ! b ) {
		return false;
	}
	if ( ! a || ! b ) {
		return true;
	}
	return (
		a.operator !== b.operator ||
		JSON.stringify( a.value ) !== JSON.stringify( b.value )
	);
};

export const FeesReport = ( {
	period,
	onReload = () => undefined,
}: FeesReportProps ): JSX.Element => {
	const [ view, setView ] = useFeesView();
	const {
		feesQuery,
		rows,
		totalItems,
		totalPages,
		methodElements,
		typeElements,
		isLoading,
		error,
	} = useFeesData( view, period );
	const { requestReportExport, isExportInProgress } = useReportExport();
	const { createNotice } = useDispatch( 'core/notices' );

	const fields = useMemo(
		() => getFeesFields( { methodElements, typeElements } ),
		[ methodElements, typeElements ]
	);
	const hasError = Object.keys( error ).length > 0;
	const hasFilters = ( view.filters ?? [] ).length > 0 || !! view.search;
	const isEmpty =
		! isLoading && ! hasError && rows.length === 0 && ! hasFilters;

	// Move focus to the error region and announce when an error surfaces, so
	// keyboard/AT users notice the table disappearing. `role="alert"` on the
	// container takes care of automatic announcement; the focus move handles
	// keyboard context.
	const errorHeadingRef = useRef< HTMLHeadingElement >( null );
	const previousErrorRef = useRef( hasError );
	useEffect( () => {
		if ( hasError && ! previousErrorRef.current ) {
			errorHeadingRef.current?.focus();
		}
		previousErrorRef.current = hasError;
	}, [ hasError ] );

	// Announce "Fees report loaded" to AT users on every loading→ready edge.
	const previousLoadingRef = useRef( isLoading );
	useEffect( () => {
		if ( previousLoadingRef.current && ! isLoading && ! hasError ) {
			speak(
				sprintf(
					/* translators: %d: number of fees loaded into the report table. */
					__( '%d fees loaded.', 'woocommerce-payments' ),
					totalItems
				)
			);
		}
		previousLoadingRef.current = isLoading;
	}, [ isLoading, hasError, totalItems ] );

	const handleViewChange = useCallback(
		( next: View ) => {
			if ( haveFieldsChanged( view.fields, next.fields ) ) {
				recordEvent( 'wcpay_reports_view_options_opened', {
					report: 'fees',
				} );
			}
			if (
				haveDateFiltersChanged( view.filters ?? [], next.filters ?? [] )
			) {
				recordEvent( 'wcpay_reports_date_range_changed', {
					report: 'fees',
				} );
			}
			setView( next );
		},
		[ setView, view.fields, view.filters ]
	);

	if ( hasError ) {
		return (
			<div
				className="wcpay-reports-state wcpay-reports-state--error"
				role="alert"
				aria-labelledby="wcpay-reports-fees-error"
			>
				<h2
					id="wcpay-reports-fees-error"
					ref={ errorHeadingRef }
					tabIndex={ -1 }
				>
					{ __( 'Fees report unavailable', 'woocommerce-payments' ) }
				</h2>
				<Button variant="secondary" onClick={ onReload }>
					{ __( 'Reload report', 'woocommerce-payments' ) }
				</Button>
			</div>
		);
	}

	if ( isEmpty ) {
		return (
			<div className="wcpay-reports-state wcpay-reports-state--empty">
				<h2>{ __( 'No fees yet', 'woocommerce-payments' ) }</h2>
				<p>
					{ __(
						'Fees will appear here once you start receiving payments.',
						'woocommerce-payments'
					) }
				</p>
			</div>
		);
	}

	const handleExport = () => {
		recordEvent( 'wcpay_reports_export_click', {
			report: 'fees',
			exported_row_count: totalItems,
		} );

		const userEmail = wcpaySettings.currentUserEmail;
		const locale = wcSettings.locale.userLocale;
		const exportRequestURL = getReportsFeesCSVRequestURL( {
			match: feesQuery.match,
			dateBefore: feesQuery.date_before,
			dateAfter: feesQuery.date_after,
			dateBetween: feesQuery.date_between,
			paymentMethodType: feesQuery.payment_method_type,
			type: feesQuery.type,
			search: feesQuery.search,
			orderby: feesQuery.orderby || 'date',
			order: feesQuery.order || 'desc',
			userEmail,
			locale,
		} );

		const confirmThreshold = 10000;
		const confirmMessage = sprintf(
			__(
				"You are about to export %d fees. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?",
				'woocommerce-payments'
			),
			totalItems
		);

		if (
			hasFilters ||
			totalItems < confirmThreshold ||
			window.confirm( confirmMessage )
		) {
			requestReportExport( {
				exportRequestURL,
				exportFileAvailabilityEndpoint: reportsFeesDownloadEndpoint,
				userEmail,
			} );

			createNotice(
				'success',
				sprintf(
					__(
						"🎉 We're processing your export. The file will download automatically and be emailed to %s.",
						'woocommerce-payments'
					),
					userEmail
				)
			);
		}
	};

	return (
		<div className="wcpay-reports-fees">
			<div className="wcpay-reports-fees__toolbar">
				<DownloadButton
					isDisabled={
						isLoading || isExportInProgress || rows.length === 0
					}
					isBusy={ isExportInProgress }
					onClick={ handleExport }
				/>
			</div>
			<DataViews
				data={ rows }
				view={ view }
				onChangeView={ handleViewChange }
				fields={ fields }
				paginationInfo={ { totalItems, totalPages } }
				isLoading={ isLoading }
				defaultLayouts={ { table: {} } }
				search
				searchLabel={ __(
					'Search by transaction ID, order ID, or payout ID',
					'woocommerce-payments'
				) }
				getItemId={ ( item ) => item.transaction_id }
			/>
			<p className="wcpay-reports-fees__date-basis-note">
				{ __(
					'Dates reflect when each event was created - settlement-date reporting is coming.',
					'woocommerce-payments'
				) }
			</p>
		</div>
	);
};

export default FeesReport;
