/**
 * Internal dependencies
 */
import Page from 'components/page';
import TransactionsFilters from './filters';
import TransactionsList from './list';
import { TestModeNotice, topics } from 'components/test-mode-notice';

export const TransactionsPage = () => {
	return (
		<Page>
			<TestModeNotice topic={ topics.transactions } />
			<TransactionsFilters />
			<TransactionsList currency={ wcpaySettings.defaultCurrency } />
		</Page>
	);
};

export default TransactionsPage;
