/**
 * Internal dependencies
 */
import Page from 'components/page';
import TransactionsFilters from './filters';
import TransactionsList from './list';

export const TransactionsPage = () => {
	return (
		<Page>
			<TransactionsFilters />
			<TransactionsList />
		</Page>
	);
};

export default TransactionsPage;
