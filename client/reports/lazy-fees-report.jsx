/** @format */

/**
 * External dependencies
 */
import React, { lazy, Suspense } from 'react';
import { __ } from '@wordpress/i18n';

const LazyFeesReport = lazy( () =>
	import( /* webpackChunkName: "reports-fees" */ './fees' ).then(
		( { FeesReport } ) => ( { default: FeesReport } )
	)
);

export const LoadingReportState = ( { headingRef, headingTabIndex } = {} ) => (
	<div
		className="wcpay-reports-state wcpay-reports-state--loading"
		role="status"
	>
		<h2 ref={ headingRef } tabIndex={ headingTabIndex }>
			{ __( 'Loading report', 'woocommerce-payments' ) }
		</h2>
	</div>
);

export const LazyLoadedFeesReport = ( { onReload } ) => (
	<Suspense fallback={ <LoadingReportState /> }>
		<LazyFeesReport onReload={ onReload } />
	</Suspense>
);
