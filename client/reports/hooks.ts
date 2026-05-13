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

// Shell placeholder — replaced once real selectors land. Until then, reload
// no-ops on unregistered resolvers (invalidateResolution is safe in that case).
const reportsPlaceholderSelectors: Record< ReportsTab, string > = {
	balance: 'getReportsBalanceSummary',
	fees: 'getReportsFees',
};

interface WCPayResolutionDispatch {
	invalidateResolution: ( selectorName: string, args: unknown[] ) => void;
}

export function useReportsTabReload(
	tab: ReportsTab,
	period: ReportsPeriodRange
): () => void {
	const { invalidateResolution } = useDispatch(
		WCPAY_STORE_NAME
	) as unknown as WCPayResolutionDispatch;

	return useCallback( () => {
		invalidateResolution( reportsPlaceholderSelectors[ tab ], [ period ] );
	}, [ invalidateResolution, period, tab ] );
}
