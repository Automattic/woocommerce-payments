/**
 * Internal dependencies
 */
import Page from 'components/page';
import TransactionsList from './list';
import { TestModeNotice, topics } from 'components/test-mode-notice';

export const TransactionsPage = () => {
	return (
		<Page>
			<TestModeNotice topic={ topics.transactions } />
			<TransactionsList />
		</Page>
	);
};

export default TransactionsPage;
