/** @format **/

/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { Experiment } from '@woocommerce/explat';
import { TabPanel } from '@wordpress/components';
import { getQuery, updateQueryString } from '@woocommerce/navigation';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Page from 'components/page';
import TransactionsList from './list';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import {
	EmptyStateList,
	EmptyStateTableHeaders,
} from '../empty-state-table/list';
import EmptyStateTable from 'empty-state-table';
import ListBanner from '../empty-state-table/transactions-banner.svg';
import Authorizations from './uncaptured';
import './style.scss';
import {
	useManualCapture,
	useSettings,
	useAuthorizationsSummary,
} from 'wcpay/data';
import WCPaySettingsContext from '../settings/wcpay-settings-context';
import RiskReviewList from './risk-review';

declare const window: any;

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

const defaultExperience = (
	<>
		<TestModeNotice topic={ topics.transactions } />
		<TransactionsList />
	</>
);

const treatmentExperience = wcpaySettings.accountStatus.status ? (
	defaultExperience
) : (
	<EmptyStateTable
		headers={ EmptyStateTableHeaders }
		title="Transactions"
		content={ <EmptyStateList listBanner={ ListBanner } /> }
	/>
);

const tabsComponentMap = {
	'transactions-page': (
		<Experiment
			name="wcpay_empty_state_preview_mode_v5"
			treatmentExperience={ treatmentExperience }
			defaultExperience={ defaultExperience }
		/>
	),
	'uncaptured-page': (
		<>
			<TestModeNotice topic={ topics.authorizations } />
			<Authorizations />
		</>
	),
	'review-page': (
		<>
			<TestModeNotice topic={ topics.riskReview } />
			<RiskReviewList />
		</>
	),
	'blocked-page': <p>Blocked page</p>,
};

export const TransactionsPage = (): JSX.Element => {
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
			name: 'review-page',
			title: sprintf(
				/* translators: %1: number of transactions hold for review */
				__( 'Risk Review (%1$s)', 'woocommerce-payments' ),
				authorizationsSummary.count ?? '...'
			),
			className: 'review-list',
		},
		{
			name: 'blocked-page',
			title: sprintf(
				__( 'Blocked', 'woocommerce-payments' ),
				authorizationsSummary.count ?? '...'
			),
			className: 'blocked-list',
		},
	].filter( ( item ) => {
		if ( 'uncaptured-page' !== item.name ) return true;

		return isAuthAndCaptureEnabled && shouldShowUncapturedTab;
	} );

	if ( typeof shouldShowUncapturedTab === 'undefined' )
		return tabsComponentMap[ 'transactions-page' ];

	return (
		<Page>
			<TabPanel
				className="wcpay-transactions-page"
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
		<Page>
			<WCPaySettingsContext.Provider value={ window.wcpaySettings }>
				<TransactionsPage />
			</WCPaySettingsContext.Provider>
		</Page>
	);
};
