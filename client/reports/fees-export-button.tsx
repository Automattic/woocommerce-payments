/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { useReportExport } from 'wcpay/hooks/use-report-export';
import { useReportsFeesSummary } from 'wcpay/data/reports/hooks';
import {
	feesDownloadEndpoint,
	getFeesCSVRequestURL,
} from 'wcpay/data/reports/resolvers';

/**
 * Threshold above which an unfiltered export prompts the merchant for
 * confirmation. Matches the Transactions list threshold — Fees rows are
 * 1:1 with transactions, so the same row count is the right peer.
 */
const confirmThreshold = 10000;

/**
 * Triggers a Fees CSV export for the current URL-synced filter state.
 *
 * Reads filter state from `getQuery()` so the export honours the same
 * filters as the on-screen Fees table, then delegates the async
 * POST → poll → download flow to `useReportExport` (shared with
 * Transactions, Payouts, and Disputes).
 */
export const FeesExportButton: React.FC = () => {
	const { requestReportExport, isExportInProgress } = useReportExport();
	const { createNotice } = useDispatch( 'core/notices' );
	const { feesSummary } = useReportsFeesSummary(
		getQuery() as Parameters< typeof useReportsFeesSummary >[ 0 ]
	);
	const totalRows = feesSummary?.count ?? 0;

	const onClick = () => {
		const userEmail = wcpaySettings.currentUserEmail;
		const locale = wcSettings.locale.userLocale;
		const query = getQuery() as Record< string, unknown >;

		const isFiltered =
			!! query.date_after ||
			!! query.date_before ||
			!! query.date_between ||
			!! query.payment_method_type ||
			!! query.type ||
			!! query.order_id ||
			!! query.deposit_id ||
			!! query.search;

		if (
			! isFiltered &&
			totalRows >= confirmThreshold &&
			! window.confirm(
				sprintf(
					/* translators: %d: number of fees to be exported. */
					__(
						"You are about to export %d fees. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?",
						'woocommerce-payments'
					),
					totalRows
				)
			)
		) {
			return;
		}

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
