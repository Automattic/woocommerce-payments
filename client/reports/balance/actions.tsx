/** @format */

/**
 * External dependencies
 */
import React, { useId } from 'react';
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { downloadCSVFile } from '@woocommerce/csv-export';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useReportsBalanceSummary } from 'wcpay/data';
import { getVisibleBalanceRows } from './rows';
import { useBalanceDateFilter } from './use-balance-date-filter';
import { getBalanceCSV, getBalanceExportFileName } from './format';
import {
	hasBalanceActivity,
	hasKeys,
	isBalanceSummaryMalformed,
} from './utils';

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

	const onExport = () => {
		if ( actionsDisabled ) {
			return;
		}

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
		} catch ( exportError ) {
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

		window.print();
	};

	return (
		<div className="wcpay-reports-balance-actions">
			<Button
				variant="secondary"
				disabled={ actionsDisabled }
				accessibleWhenDisabled
				onClick={ onPrint }
				__next40pxDefaultSize
			>
				{ __( 'Print', 'woocommerce-payments' ) }
			</Button>
			<Button
				variant="primary"
				disabled={ actionsDisabled }
				accessibleWhenDisabled
				onClick={ onExport }
				__next40pxDefaultSize
			>
				{ __( 'Export', 'woocommerce-payments' ) }
			</Button>
		</div>
	);
};

export default BalanceActions;
