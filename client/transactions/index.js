/** @format */

/**
 * Internal dependencies
 */
import Page from 'components/page';
import TransactionsList from './list';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import {
	EmptyStateList,
	EmptyStateTableHeaders,
} from '../emtpy-state-table/list';
import EmptyStateTable from 'emtpy-state-table';
import ListBanner from '../emtpy-state-table/transactions-banner.svg';
import { Experiment } from '@woocommerce/explat';

export const TransactionsPage = () => {
	return (
		<Page>
			<Experiment
				name="wcpay_empty_state_preview_mode_v1"
				treatmentExperience={
					<EmptyStateTable
						headers={ EmptyStateTableHeaders }
						title="Transactions"
						content={ <EmptyStateList listBanner={ ListBanner } /> }
					/>
				}
				defaultExperience={
					<>
						<TestModeNotice topic={ topics.transactions } />
						<TransactionsList />
					</>
				}
			/>
		</Page>
	);
};

export default TransactionsPage;
