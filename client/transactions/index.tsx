/**
 * External dependencies
 */
import React from 'react';
import { Experiment } from '@woocommerce/explat';
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

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

	return (
		<Page>
			<TabPanel
				className="wcpay-transactions-page"
				activeClass="active-tab"
				tabs={ [
					{
						name: 'transactions-page',
						title: __( 'Transactions', 'woocommerce-payments' ),
						className: 'transactions-list',
					},
					{
						name: 'uncaptured-page',
						title: __( 'Uncaptured', 'woocommerce-payments' ),
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
};

export default TransactionsPage;
