/**
 * External dependencies
 */
import { Component } from '@wordpress/element';
import { compose } from '@wordpress/compose';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import { formatCurrency } from '@woocommerce/currency';
import { TableCard } from '@woocommerce/components';
import { capitalize } from 'lodash';

/**
 * Internal dependencies.
 */
import withSelect from 'payments-api/with-select';

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
		const { transactions, isLoading } = this.props;
		const transactionsData = transactions.data || [];
		// Do not display table loading view if data is already available.
		const loadingStatus = ( transactionsData.length <= 0 ) && isLoading;

		const rows = transactionsData.map( ( txn ) => {
			const charge = txn.source.object === 'charge' ? txn.source : null;
			const order_url = txn.order ? <a href={ txn.order.url }>#{ txn.order.number }</a> : <span>&ndash;</span>;

			const data = {
				created: { value: txn.created * 1000, display: dateI18n( 'M j, Y / g:iA', moment( txn.created * 1000 ) ) },
				type: { value: txn.type, display: capitalize( txn.type ) },
				source: charge && { value: charge.payment_method_details.card.brand, display: <code>{ charge.payment_method_details.card.brand }</code> },
				order: { value: txn.order, display: order_url },
				customer: charge && { value: charge.billing_details.name, display: charge.billing_details.name },
				email: charge && { value: charge.billing_details.email, display: charge.billing_details.email },
				country: charge && { value: charge.billing_details.address.country, display: charge.billing_details.address.country },
				amount: { value: txn.amount / 100, display: formatCurrency( txn.amount / 100 ) },
				fee: { value: txn.fee / 100, display: formatCurrency( txn.fee / 100 ) },
				net: { value: ( txn.amount - txn.fee ) / 100, display: formatCurrency( ( txn.amount - txn.fee ) / 100 ) },
				// TODO deposit: { value: available_on * 1000, display: dateI18n( 'Y-m-d H:i', moment( available_on * 1000 ) ) },
				risk_level: charge && { value: charge.outcome.risk_level, display: capitalize( charge.outcome.risk_level ) },
			};

			return headers.map( ( { key } ) => data[ key ] || { display: null } );
		} );

		return (
			<TableCard
				title="Transactions"
				isLoading={ loadingStatus }
				rowsPerPage={ 10 }
				totalRows={ 10 }
				headers={ headers }
				rows={ rows }
			/>
		);
	}
};

export default compose(
	withSelect( select => {
		const { getTransactions, getTransactionsIsLoading } = select( 'wc-payments-api' );
		const transactions = getTransactions();
		const isLoading = getTransactionsIsLoading();

		return { transactions, isLoading };
	} )
)( TransactionsList );
