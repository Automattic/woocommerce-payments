/** @format */

/**
 * External dependencies
 */
import React, { lazy, Suspense } from 'react';

const LazyBalanceActions = lazy( () =>
	import(
		/* webpackChunkName: "reports-balance-actions" */ './balance/actions'
	).then( ( { BalanceActions } ) => ( { default: BalanceActions } ) )
);

export const LazyLoadedBalanceActions = () => (
	<Suspense fallback={ null }>
		<LazyBalanceActions />
	</Suspense>
);
