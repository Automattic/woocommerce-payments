/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { TabPanel } from '@wordpress/components';
import { getQuery, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import Page from 'components/page';
import { ReportsHeader } from './header';
import { getLastFullCalendarMonthUTC } from './period-selector';
import { reportsTabs, ReportsTabPanel, normalizeReportsTab } from './tabs';
import { useReportsTabState } from './hooks';
import type { ReportsTabStatus } from './types';
import './style.scss';

interface ReportsPageProps {
	initialTabStatus?: ReportsTabStatus;
	now?: Date;
}

export const ReportsPage: React.FC< ReportsPageProps > = ( {
	initialTabStatus = 'empty',
	now = new Date(),
} ) => {
	const currentQuery = getQuery();
	const activeTab = normalizeReportsTab( currentQuery.tab );
	const period = getLastFullCalendarMonthUTC( now );
	const { status, reload } = useReportsTabState(
		activeTab,
		period,
		initialTabStatus
	);

	const onTabSelected = ( tab: string ) => {
		updateQueryString(
			{
				tab,
			},
			'/payments/reports'
		);
	};

	return (
		<Page className="wcpay-reports-page">
			<ReportsHeader />
			<TabPanel
				className="wcpay-reports-tab-panel"
				activeClass="active-tab"
				onSelect={ onTabSelected }
				initialTabName={ activeTab }
				tabs={ reportsTabs }
			>
				{ ( tab ) => (
					<div className="wcpay-reports-content">
						<ReportsTabPanel
							tab={ normalizeReportsTab( tab.name ) }
							status={ status }
							onReload={ reload }
						/>
					</div>
				) }
			</TabPanel>
		</Page>
	);
};

export default ReportsPage;
