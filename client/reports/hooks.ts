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
): () => void {
	const { invalidateResolution, invalidateResolutionForStoreSelector } =
		useDispatch( WCPAY_STORE_NAME ) as unknown as WCPayResolutionDispatch;

	return useCallback( () => {
		if ( tab === 'fees' ) {
			// Invalidate all cached resolutions for the fees selector so
			// every in-flight or cached query is re-fetched on reload.
			invalidateResolutionForStoreSelector( 'getReportsFees' );
			invalidateResolutionForStoreSelector( 'getReportsFeesSummary' );
		} else {
			invalidateResolution( 'getReportsBalanceSummary', [ period ] );
		}
	}, [
		invalidateResolution,
		invalidateResolutionForStoreSelector,
		period,
		tab,
	] );
}
