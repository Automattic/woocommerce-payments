/**
 * Internal dependencies
 */
import Page from 'components/page';
import TransactionFilters from './filters';
import TransactionsList from './list';

export const TransactionsPage = () => {
	return (
		<Page>
			<TransactionFilters />
			<TransactionsList />
		</Page>
	);
};

export default TransactionsPage;
