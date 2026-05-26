/** @format */

/**
 * External dependencies
 */
import React, { useEffect, useMemo, useRef, useState } from 'react';
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
import { BalanceDateFilterNowContext } from './balance/context';
import type { ReportsTab } from './types';
import './style.scss';

interface ReportsPageProps {
	now?: Date;
}

export const ReportsPage: React.FC< ReportsPageProps > = ( { now } ) => {
	const [ activeTab, setActiveTab ] = useState( () =>
		normalizeReportsTab( getQuery().tab )
	);
	const [ tabPanelKey, setTabPanelKey ] = useState( 0 );
	const dateFilterNow = useRef( now ?? new Date() ).current;
	const tabPanelWrapperRef = useRef< HTMLDivElement >( null );
	const previousActiveTabRef = useRef< ReportsTab >( activeTab );
	const period = useMemo(
		() => getLastFullCalendarMonthUTC( dateFilterNow ),
		[ dateFilterNow ]
	);
	const reload = useReportsTabReload( activeTab, period );

	useEffect( () => {
		const syncActiveTabFromUrl = () => {
			const nextTab = normalizeReportsTab( getQuery().tab );

			setActiveTab( nextTab );
			setTabPanelKey( ( key ) => key + 1 );
		};

		window.addEventListener( 'popstate', syncActiveTabFromUrl );
		return () => {
			window.removeEventListener( 'popstate', syncActiveTabFromUrl );
		};
	}, [] );

	useEffect( () => {
		if ( previousActiveTabRef.current !== activeTab ) {
			tabPanelWrapperRef.current
				?.querySelector< HTMLElement >(
					'[role="tab"][aria-selected="true"]'
				)
				?.focus();
		}

		previousActiveTabRef.current = activeTab;
	}, [ activeTab ] );

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
			<BalanceDateFilterNowContext.Provider value={ dateFilterNow }>
				<ReportsHeader activeTab={ activeTab } />
				<div ref={ tabPanelWrapperRef }>
					<TabPanel
						key={ tabPanelKey }
						className="wcpay-reports-tab-panel"
						activeClass="active-tab"
						onSelect={ onTabSelected }
						initialTabName={ activeTab }
						tabs={ reportsTabs }
					>
						{ ( tab ) => (
							<div className="wcpay-reports-content">
								<ReportsTabPanel
									tab={ tab.name as ReportsTab }
									onReload={ reload }
								/>
							</div>
						) }
					</TabPanel>
				</div>
			</BalanceDateFilterNowContext.Provider>
		</Page>
	);
};

export default ReportsPage;
