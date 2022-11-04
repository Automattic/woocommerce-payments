/**
 * External dependencies
 */
import React from 'react';
import { Experiment } from '@woocommerce/explat';
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getQuery, updateQueryString } from '@woocommerce/navigation';

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
import { useAuthorizationsSummary } from 'wcpay/data';
import ListBanner from '../empty-state-table/transactions-banner.svg';
import Authorizations from './uncaptured';
import './style.scss';

const displayAuthorizations = false;

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

export const TransactionsPage = (): JSX.Element => {
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

	const { authorizationsSummary } = useAuthorizationsSummary( currentQuery );

	if ( displayAuthorizations ) {
		return (
			<Page>
				<TabPanel
					className="wcpay-transactions-page"
					activeClass="active-tab"
					onSelect={ onTabSelected }
					initialTabName={ initialTab || 'transactions-page' }
					tabs={ [
						{
							name: 'transactions-page',
							title: __( 'Transactions', 'woocommerce-payments' ),
							className: 'transactions-list',
						},
						{
							name: 'uncaptured-page',
							// TODO: Find way to show a proper badge (as in menu) and convert back to translated string.
							title: `Uncaptured (${
								authorizationsSummary.count ?? '...'
							})`,
							className: 'authorizations-list',
						},
					] }
				>
					{ ( tab ) => {
						if ( 'uncaptured-page' === tab.name ) {
							return <Authorizations />;
						}

						return (
							<Experiment
								name="wcpay_empty_state_preview_mode_v5"
								treatmentExperience={ treatmentExperience }
								defaultExperience={ defaultExperience }
							/>
						);
					} }
				</TabPanel>
			</Page>
		);
	}

	return (
		<Experiment
			name="wcpay_empty_state_preview_mode_v5"
			treatmentExperience={ treatmentExperience }
			defaultExperience={ defaultExperience }
		/>
	);
};

export default TransactionsPage;
