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
import type { ReportsTab, ReportsTabStatus } from './types';

// Shell placeholder — replaced once real selectors land. Until then, status
// echoes whatever the caller passes in and reload no-ops on unregistered
// resolvers (invalidateResolutionForStoreSelector is safe in that case).
const reportsPlaceholderSelectors: Record< ReportsTab, string > = {
	balance: 'getReportsBalanceSummary',
	fees: 'getReportsFees',
};

interface WCPayResolutionDispatch {
	invalidateResolutionForStoreSelector: (
		selectorName: string,
		args?: unknown[]
	) => void;
}

export function useReportsTabState(
	tab: ReportsTab,
	period: ReportsPeriodRange,
	status: ReportsTabStatus = 'empty'
): { status: ReportsTabStatus; reload: () => void } {
	const { invalidateResolutionForStoreSelector } = useDispatch(
		WCPAY_STORE_NAME
	) as unknown as WCPayResolutionDispatch;

	const reload = useCallback( () => {
		invalidateResolutionForStoreSelector(
			reportsPlaceholderSelectors[ tab ],
			[ period ]
		);
	}, [ invalidateResolutionForStoreSelector, period, tab ] );

	return {
		status,
		reload,
	};
}
