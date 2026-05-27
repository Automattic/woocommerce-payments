/** @format */

/**
 * External dependencies
 */
import { useCallback } from 'react';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME as WCPAY_STORE_NAME } from 'wcpay/data/constants';
import type { ReportsPeriodRange } from './period-selector';
import type { ReportsTab } from './types';

interface WCPayResolutionDispatch {
	invalidateResolution: ( selectorName: string, args: unknown[] ) => void;
	invalidateResolutionForStoreSelector: ( selectorName: string ) => void;
}

export function useReportsTabReload(
	tab: ReportsTab,
	period: ReportsPeriodRange
): ( periodOverride?: ReportsPeriodRange ) => void {
	const { invalidateResolution, invalidateResolutionForStoreSelector } =
		useDispatch( WCPAY_STORE_NAME ) as unknown as WCPayResolutionDispatch;
	const currency = (
		wcpaySettings.accountDefaultCurrency || ''
	).toLowerCase();

	return useCallback(
		( periodOverride?: ReportsPeriodRange ) => {
			if ( tab === 'fees' ) {
				// Invalidate all cached resolutions for the fees selector so
				// every in-flight or cached query is re-fetched on reload.
				invalidateResolutionForStoreSelector( 'getReportsFees' );
				invalidateResolutionForStoreSelector( 'getReportsFeesSummary' );
			} else {
				const balancePeriod = periodOverride ?? period;

				invalidateResolution( 'getReportsBalanceSummary', [
					{
						dateStart: balancePeriod.start,
						dateEnd: balancePeriod.end,
						currency,
					},
				] );
			}
		},
		[
			currency,
			invalidateResolution,
			invalidateResolutionForStoreSelector,
			period,
			tab,
		]
	);
}
