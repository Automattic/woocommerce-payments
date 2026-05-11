/** @format */

/**
 * External dependencies
 */
import React, { useEffect, useMemo, useState } from 'react';
import { TabPanel } from '@wordpress/components';
import { getQuery, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import Page from 'components/page';
import { ReportsHeader } from './header';
import { getLastFullCalendarMonthUTC } from './period-selector';
import { reportsTabs, ReportsTabPanel, normalizeReportsTab } from './tabs';
import { useReportsTabReload } from './hooks';
import type { ReportsTabStatus } from './types';
import './style.scss';

interface ReportsPageProps {
	initialTabStatus?: ReportsTabStatus;
	now?: Date;
}

export const ReportsPage: React.FC< ReportsPageProps > = ( {
	initialTabStatus = 'empty',
	now,
} ) => {
	const [ activeTab, setActiveTab ] = useState( () =>
		normalizeReportsTab( getQuery().tab )
	);
	const period = useMemo(
		() => getLastFullCalendarMonthUTC( now ?? new Date() ),
		[ now ]
	);
	const reload = useReportsTabReload( activeTab, period );

	useEffect( () => {
		const syncActiveTabFromUrl = () => {
			setActiveTab( normalizeReportsTab( getQuery().tab ) );
		};

		window.addEventListener( 'popstate', syncActiveTabFromUrl );
		return () => {
			window.removeEventListener( 'popstate', syncActiveTabFromUrl );
		};
	}, [] );

	const onTabSelected = ( tab: string ) => {
		const nextTab = normalizeReportsTab( tab );

		if ( nextTab === activeTab ) {
			return;
		}

		setActiveTab( nextTab );
		updateQueryString(
			{
				tab: nextTab,
			},
			'/payments/reports'
		);
	};

	return (
		<Page className="wcpay-reports-page">
			<ReportsHeader />
			<TabPanel
				key={ activeTab }
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
							status={ initialTabStatus }
							onReload={ reload }
						/>
					</div>
				) }
			</TabPanel>
		</Page>
	);
};

export default ReportsPage;
