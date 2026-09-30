/** @format */

/**
 * External dependencies
 */
import React, { lazy, Suspense } from 'react';

/**
 * Internal dependencies
 */
import { LoadingReportState } from './loading-report-state';

const LazyFeesReport = lazy( () =>
	import( /* webpackChunkName: "reports-fees" */ './fees' ).then(
		( { FeesReport } ) => ( { default: FeesReport } )
	)
);

export const LazyLoadedFeesReport = ( { onReload } ) => (
	<Suspense fallback={ <LoadingReportState /> }>
		<LazyFeesReport onReload={ onReload } />
	</Suspense>
);
