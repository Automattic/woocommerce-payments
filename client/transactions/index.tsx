/** @format **/

/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { TabPanel } from '@wordpress/components';
import { getQuery, updateQueryString } from '@woocommerce/navigation';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Page from 'components/page';
import TransactionsList from './list';
import { TestModeNotice } from 'components/test-mode-notice';
import Authorizations from './uncaptured';
import './style.scss';
import {
	useManualCapture,
	useSettings,
	useAuthorizationsSummary,
} from 'wcpay/data';
import WCPaySettingsContext from '../settings/wcpay-settings-context';
import BlockedList from './blocked';

declare const window: any;

export const TransactionsPage: React.FC = () => {
	const currentQuery = getQuery();
	const initialTab = currentQuery.tab ?? null;

	const onTabSelected = ( tab: string ) => {
		// When switching tabs, make sure to revert the query strings to default values
		updateQueryString(
			{
				paged: '1',
				per_page: '25',
				order: '',
				orderby: '',
				tab: tab,
			},
			'/payments/transactions'
		);
	};

	const tabsComponentMap = {
		'transactions-page': (
			<>
				<TransactionsList />
			</>
		),
		'uncaptured-page': (
			<>
				<Authorizations />
			</>
		),
		'blocked-page': (
			<>
				<BlockedList />
			</>
		),
	};

	const {
		featureFlags: { isAuthAndCaptureEnabled },
	} = useContext( WCPaySettingsContext );
	const [ getIsManualCaptureEnabled ] = useManualCapture();
	const { isLoading: isLoadingSettings } = useSettings();
	const { authorizationsSummary } = useAuthorizationsSummary( {} );

	// The Uncaptured authorizations screen will be shown only if:
	// 1. The feature is turned on for all accounts
	// 2. Manual capture settings are turned on for this store
	// OR
	// 2'. There are authorizations to capture (even if the manual capture is turned off)
	const shouldShowUncapturedTab =
		( ! isLoadingSettings && getIsManualCaptureEnabled ) ||
		( authorizationsSummary.total && authorizationsSummary.total > 0 );

	const tabs = [
		{
			name: 'transactions-page',
			title: __( 'Transactions', 'woocommerce-payments' ),
			className: 'transactions-list',
		},
		{
			name: 'uncaptured-page',
			title: sprintf(
				/* translators: %1: number of uncaptured authorizations */
				__( 'Uncaptured (%1$s)', 'woocommerce-payments' ),
				authorizationsSummary.count ?? '...'
			),
			className: 'authorizations-list',
		},
		{
			name: 'blocked-page',
			title: __( 'Blocked', 'woocommerce-payments' ),
			className: 'blocked-list',
		},
	].filter( ( item ) => {
		if ( 'uncaptured-page' !== item.name ) return true;

		return isAuthAndCaptureEnabled && shouldShowUncapturedTab;
	} );

	return (
		<Page className="wcpay-transactions-page">
			<TestModeNotice currentPage="transactions" />
			<TabPanel
				activeClass="active-tab"
				onSelect={ onTabSelected }
				initialTabName={ initialTab || 'transactions-page' }
				tabs={ tabs }
			>
				{ ( tab ) => {
					return (
						tabsComponentMap[
							tab.name as keyof typeof tabsComponentMap
						] || tabsComponentMap[ 'transactions-page' ]
					);
				} }
			</TabPanel>
		</Page>
	);
};

export default (): JSX.Element => {
	return (
		<WCPaySettingsContext.Provider value={ window.wcpaySettings }>
			<TransactionsPage />
		</WCPaySettingsContext.Provider>
	);
};
