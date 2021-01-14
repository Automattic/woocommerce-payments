/**
 * Internal dependencies
 */
import Page from 'components/page';
import TransactionsFilters from './filters';
import TransactionsList from './list';
import { TestNotice, topics } from 'components/test-mode-notice';

export const TransactionsPage = () => {
	return (
		<Page>
			<TestNotice topic={ topics.transactions } />
			<TransactionsFilters />
			<TransactionsList />
		</Page>
	);
};

export default TransactionsPage;
