/**
 * External dependencies
 */
import React from 'react';

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
import { Experiment } from '@woocommerce/explat';

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
			<Experiment
				name="wcpay_empty_state_preview_mode_v2"
				treatmentExperience={ treatmentExperience }
				defaultExperience={ defaultExperience }
			/>
		</Page>
	);
};

export default TransactionsPage;
