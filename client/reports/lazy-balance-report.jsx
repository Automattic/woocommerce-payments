/** @format */

/**
 * External dependencies
 */
import React, { lazy, Suspense } from 'react';

/**
 * Internal dependencies
 */
import { LoadingReportState } from './loading-report-state';

const LazyBalanceReport = lazy( () =>
	import( /* webpackChunkName: "reports-balance" */ './balance' ).then(
		( { BalanceReport } ) => ( { default: BalanceReport } )
	)
);

export const LazyLoadedBalanceReport = ( { onReload } ) => (
	<Suspense fallback={ <LoadingReportState /> }>
		<LazyBalanceReport onReload={ onReload } />
	</Suspense>
);
