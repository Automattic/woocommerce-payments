/** @format */

/**
 * External dependencies
 */
import { useCallback } from 'react';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { store as reportsStore } from 'wcpay/data/reports';
import type { ReportsPeriodRange } from './period-selector';
import type { ReportsTab } from './types';

interface WCPayResolutionDispatch {
	invalidateResolution: ( selectorName: string, args: unknown[] ) => void;
	invalidateResolutionForStoreSelector: ( selectorName: string ) => void;
}

export function useReportsTabReload(
	tab: ReportsTab,
	period: ReportsPeriodRange,
	currency: string
): ( periodOverride?: ReportsPeriodRange ) => void {
	const { invalidateResolution, invalidateResolutionForStoreSelector } =
		useDispatch( reportsStore ) as unknown as WCPayResolutionDispatch;
	const normalizedCurrency = currency.toLowerCase();

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
						currency: normalizedCurrency,
					},
				] );
			}
		},
		[
			invalidateResolution,
			invalidateResolutionForStoreSelector,
			normalizedCurrency,
			period,
			tab,
		]
	);
}
