/**
 * External dependencies
 */
import { Component } from '@wordpress/element';
import { TableCard } from '@woocommerce/components';

const headers = [
	{ key: 'created', label: 'Date / Time', required: true, isLeftAligned: true, defaultSort: true, defaultOrder: 'desc' },
	{ key: 'type', label: 'Type', required: true },
	{ key: 'source', label: 'Source' },
	{ key: 'order', label: 'Order #', required: true },
	{ key: 'customer', label: 'Customer' },
	{ key: 'email', label: 'Email', hiddenByDefault: true },
	{ key: 'country', label: 'Country', hiddenByDefault: true },
	{ key: 'amount', label: 'Amount', isNumeric: true },
	{ key: 'fee', label: 'Fees', isNumeric: true },
	{ key: 'net', label: 'Net', isNumeric: true, required: true },
	// TODO { key: 'deposit', label: 'Deposit', required: true },
	{ key: 'risk_level', label: 'Risk Level', hiddenByDefault: true },
];

class TransactionsList extends Component {
	render() {
		return (
			<TableCard
				title="Transactions"
				isLoading={ false }
				rowsPerPage={ 10 }
				totalRows={ 10 }
				headers={ headers }
				rows={ [] }
			/>
		);
	}
};

export default TransactionsList;
