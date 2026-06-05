/** @format */

/**
 * External dependencies
 */
import React, { RefObject } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { BalanceSummaryTable } from './summary-table';
import { getVisibleBalanceRows } from './rows';
import type { ReportsBalanceSummary } from 'wcpay/data/reports/hooks';

interface BalanceLoadingSkeletonProps {
	headingRef?: RefObject< HTMLHeadingElement >;
	headingTabIndex?: number;
}

// Static mock summary used only to render plausible amounts behind the blur.
// Values are illustrative: no PII, no real merchant data.
const mockSummaryFixture: ReportsBalanceSummary = {
	currency: 'usd',
	period: {
		start: '2024-03-01T00:00:00',
		end: '2024-03-31T23:59:59',
	},
	starting_balance: { amount: 120000 },
	total_charges_captured: { amount: 845000, count: 42 },
	fees: { amount: -28400 },
	charge_fees: { amount: -25400 },
	dispute_fees: { amount: -3000 },
	refunds: { amount: -14500, count: 4 },
	disputes: { amount: -7500, count: 1 },
	net_balance_change_in_the_period: { amount: 794600 },
	payouts: { amount: 800000, count: 2 },
	ending_balance: { amount: 114600 },
};

export const BalanceLoadingSkeleton = ( {
	headingRef,
	headingTabIndex,
}: BalanceLoadingSkeletonProps ): JSX.Element => {
	const currency =
		wcpaySettings?.accountDefaultCurrency ||
		mockSummaryFixture.currency ||
		'usd';
	const mockSummary: ReportsBalanceSummary = {
		...mockSummaryFixture,
		currency,
	};
	const visibleRows = getVisibleBalanceRows( mockSummary );

	return (
		<div className="wcpay-reports-balance__skeleton">
			<BalanceSummaryTable
				visibleRows={ visibleRows }
				summary={ mockSummary }
				displayPeriod={ {
					start: mockSummary.period?.start ?? '',
					end: mockSummary.period?.end ?? '',
				} }
				currency={ currency }
				ariaHidden
			/>
			<div
				className="wcpay-reports-balance__skeleton-shimmer"
				aria-hidden="true"
			/>
			<div
				role="status"
				className="wcpay-reports-balance__skeleton-status"
			>
				<h2 ref={ headingRef } tabIndex={ headingTabIndex }>
					{ __( 'Loading balance report', 'woocommerce-payments' ) }
				</h2>
			</div>
		</div>
	);
};
