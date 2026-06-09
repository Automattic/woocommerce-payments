/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { ReportsTab } from './types';
import { LazyLoadedBalanceReport } from './lazy-balance-report';
import { LazyLoadedFeesReport } from './lazy-fees-report';
import type { ReportsPeriodRange } from './period-selector';

interface ReportsTabPanelProps {
	tab: ReportsTab;
	onReload: ( periodOverride?: ReportsPeriodRange ) => void;
}

export const reportsTabs: Array< {
	name: ReportsTab;
	title: string;
	className: string;
} > = [
	{
		name: 'balance',
		title: __( 'Balance', 'woocommerce-payments' ),
		className: 'wcpay-reports-tab--balance',
	},
	{
		name: 'fees',
		title: __( 'Fees', 'woocommerce-payments' ),
		className: 'wcpay-reports-tab--fees',
	},
];

export function normalizeReportsTab( tab?: unknown ): ReportsTab {
	return tab === 'fees' ? 'fees' : 'balance';
}

export const ReportsTabPanel: React.FC< ReportsTabPanelProps > = ( {
	tab,
	onReload,
} ) => {
	if ( tab === 'fees' ) {
		return <LazyLoadedFeesReport onReload={ onReload } />;
	}

	return <LazyLoadedBalanceReport onReload={ onReload } />;
};
