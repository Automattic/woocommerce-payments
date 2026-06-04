/** @format */

/**
 * External dependencies
 */
import React, { useId } from 'react';
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { downloadCSVFile } from '@woocommerce/csv-export';
import { __ } from '@wordpress/i18n';
import { recordEvent } from 'tracks';

/**
 * Internal dependencies
 */
import { useReportsBalanceSummary } from 'wcpay/data';
import { getVisibleBalanceRows } from './rows';
import { useBalanceDateFilter } from './use-balance-date-filter';
import { getBalanceCSV, getBalanceExportFileName } from './format';
import {
	getRangeDays,
	hasBalanceActivity,
	hasKeys,
	isBalanceSummaryMalformed,
} from './utils';

const getActionEventPayload = (
	period: { start: string; end: string },
	currency: string,
	visibleRowCount: number
) => ( {
	currency,
	visible_row_count: visibleRowCount,
	range_days: getRangeDays( period.start, period.end ),
} );

const getExportErrorMessage = ( exportError: unknown ): string => {
	if ( ! ( exportError instanceof Error ) ) {
		return 'unknown';
	}

	return exportError.message.slice( 0, 200 );
};

/**
 * Toolbar component for the Balance report.
 *
 * Invariant: `BalanceActions` must only be mounted as a sibling of
 * `BalanceReport` inside the same `BalanceDateFilterNowContext.Provider`.
 * Two pieces depend on this co-location:
 *   - both components read `useBalanceDateFilter()` from the same context, so
 *     the date filter they show is the date filter the report renders;
 *   - the Print action calls `window.print()` and relies on the
 *     `wcpay-reports-balance-print-context` class that `BalanceReport`'s
 *     useEffect applies to `<body>` / `<html>`. Rendering `BalanceActions`
 *     without `BalanceReport` (storybook, isolated test, future layout
 *     change) would produce silently unstyled print output.
 */
export const BalanceActions = (): JSX.Element => {
	const { period, hasDateFilterValue } = useBalanceDateFilter();
	const { createNotice } = useDispatch( 'core/notices' );
	const {
		summary,
		error = {},
		isLoading,
	} = useReportsBalanceSummary(
		hasDateFilterValue ? period : undefined,
		wcpaySettings.accountDefaultCurrency || ''
	);
	const hasStoreError = hasKeys( error );
	const visibleRows = getVisibleBalanceRows( summary );
	const hasActivity = hasBalanceActivity( visibleRows, summary );
	const hasMalformedSummary = isBalanceSummaryMalformed( {
		summary,
		hasDateFilterValue,
		isLoading,
		hasStoreError,
	} );
	const displayPeriod = {
		start: summary.period?.start ?? period.start,
		end: summary.period?.end ?? period.end,
	};
	const currency = summary.currency ?? '';
	const actionsDisabled =
		! hasDateFilterValue ||
		isLoading ||
		hasStoreError ||
		hasMalformedSummary ||
		! hasActivity;
	const disabledHelpId = useId();
	const getDisabledHelpText = (): string => {
		if ( ! hasDateFilterValue ) {
			return __(
				'Select a date range to enable Print and Export.',
				'woocommerce-payments'
			);
		}
		if ( isLoading ) {
			return __(
				'Print and Export are available once the Balance report finishes loading.',
				'woocommerce-payments'
			);
		}
		if ( hasStoreError || hasMalformedSummary ) {
			return __(
				'Print and Export are unavailable while the Balance report is unavailable.',
				'woocommerce-payments'
			);
		}
		if ( ! hasActivity ) {
			return __(
				'Print and Export are unavailable when the selected range has no Balance activity.',
				'woocommerce-payments'
			);
		}
		return '';
	};
	const disabledHelpText = getDisabledHelpText();

	const onExport = () => {
		if ( actionsDisabled ) {
			return;
		}

		const payload = getActionEventPayload(
			displayPeriod,
			currency,
			visibleRows.length
		);

		recordEvent( 'wcpay_reports_balance_export_click', payload );

		try {
			downloadCSVFile(
				getBalanceExportFileName( displayPeriod ),
				getBalanceCSV( {
					visibleRows,
					summary,
					displayPeriod,
					currency,
				} )
			);
			recordEvent( 'wcpay_reports_balance_export_success', payload );
		} catch ( exportError ) {
			recordEvent( 'wcpay_reports_balance_export_error', {
				...payload,
				error_message: getExportErrorMessage( exportError ),
			} );
			// eslint-disable-next-line no-console
			console.error( 'Balance CSV export failed:', exportError );
			createNotice(
				'error',
				__(
					'There was a problem generating your export.',
					'woocommerce-payments'
				)
			);
		}
	};

	const onPrint = () => {
		if ( actionsDisabled ) {
			return;
		}

		recordEvent(
			'wcpay_reports_balance_print_click',
			getActionEventPayload( displayPeriod, currency, visibleRows.length )
		);

		window.print();
	};

	return (
		<div className="wcpay-reports-balance-actions">
			{ actionsDisabled && (
				<span id={ disabledHelpId } className="screen-reader-text">
					{ disabledHelpText }
				</span>
			) }
			<Button
				variant="secondary"
				disabled={ actionsDisabled }
				accessibleWhenDisabled
				onClick={ onPrint }
				aria-describedby={
					actionsDisabled ? disabledHelpId : undefined
				}
				__next40pxDefaultSize
			>
				{ __( 'Print', 'woocommerce-payments' ) }
			</Button>
			<Button
				variant="primary"
				disabled={ actionsDisabled }
				accessibleWhenDisabled
				onClick={ onExport }
				aria-describedby={
					actionsDisabled ? disabledHelpId : undefined
				}
				__next40pxDefaultSize
			>
				{ __( 'Export', 'woocommerce-payments' ) }
			</Button>
		</div>
	);
};

export default BalanceActions;
