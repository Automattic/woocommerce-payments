/** @format */

/**
 * External dependencies
 */
import React, { useMemo } from 'react';
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
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
import { useFeesData, viewToFeesQuery } from './use-fees-data';
import { getFeesFields } from './fields';
import type { ReportsPeriodRange } from '../period-selector';

interface FeesReportProps {
	period: ReportsPeriodRange;
	onReload?: () => void;
}

export const FeesReport = ( {
	period,
	onReload = () => undefined,
}: FeesReportProps ): JSX.Element => {
	const [ view, setView ] = useFeesView( period );
	const {
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

	if ( hasError ) {
		return (
			<div
				className="wcpay-reports-state wcpay-reports-state--error"
				role="group"
				aria-labelledby="wcpay-reports-fees-error"
			>
				<h2 id="wcpay-reports-fees-error">
					{ __( 'Fees report unavailable', 'woocommerce-payments' ) }
				</h2>
				<Button variant="secondary" onClick={ onReload }>
					{ __( 'Reload report', 'woocommerce-payments' ) }
				</Button>
			</div>
		);
	}

	const handleViewChange = ( next: typeof view ) => {
		if ( JSON.stringify( next.fields ) !== JSON.stringify( view.fields ) ) {
			recordEvent( 'wcpay_reports_view_options_opened', {
				report: 'fees',
			} );
		}
		const prevDate = view.filters?.find( ( f ) => f.field === 'date' );
		const nextDate = next.filters?.find( ( f ) => f.field === 'date' );
		if ( JSON.stringify( prevDate ) !== JSON.stringify( nextDate ) ) {
			recordEvent( 'wcpay_reports_date_range_changed', {
				report: 'fees',
			} );
		}
		setView( next );
	};

	const handleExport = () => {
		recordEvent( 'wcpay_reports_export_click', {
			report: 'fees',
			exported_row_count: totalItems,
		} );

		const userEmail = wcpaySettings.currentUserEmail;
		const locale = wcSettings.locale.userLocale;
		const feesQuery = viewToFeesQuery( view, period );
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

		const hasFilters = ( view.filters ?? [] ).length > 0 || !! view.search;
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
						"We're processing your export. The file will download automatically and be emailed to %s.",
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
