/** @format **/

/**
 * External dependencies
 */
import { Component } from 'react';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import { formatCurrency } from '@woocommerce/currency';
import { TableCard } from '@woocommerce/components';
import { capitalize } from 'lodash';

/**
 * WooCommerce dependencies
 */
import { onQueryChange, getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
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

export class TransactionsList extends Component {
	constructor( props ) {
		super( props );
		this.props = props;
	}

	/**
	 * Takes each transaction object and parses the raw data to an object that can
	 * be used by the {TableCard} component.
	 *
	 * @param {Array} txn Raw transaction data.
	 * @returns {Array} Transactions ready for {TableCard}.
	 */
	transactionsToRows( txn ) {
		const charge = txn.source.object === 'charge' ? txn.source : null;
		const order_url = txn.order ? <a href={ txn.order.url }>#{ txn.order.number }</a> : <span>&ndash;</span>;

		// Extract nested properties from the charge.
		const billing_details = charge ? charge.billing_details : null;
		const outcome = charge ? charge.outcome : null;
		const payment_method_details = charge ? charge.payment_method_details : null;
		const address = billing_details ? billing_details.address : null;
		const card = payment_method_details ? payment_method_details.card : null;

		// Map transaction into table row.
		const data = {
			created: { value: txn.created * 1000, display: dateI18n( 'M j, Y / g:iA', moment( txn.created * 1000 ) ) },
			type: { value: txn.type, display: capitalize( txn.type ) },
			source: card && { value: card.brand, display: <code>{ card.brand }</code> },
			order: { value: txn.order, display: order_url },
			customer: billing_details && { value: billing_details.name, display: billing_details.name },
			email: billing_details && { value: billing_details.email, display: billing_details.email },
			country: address && { value: address.country, display: address.country },
			amount: { value: txn.amount / 100, display: formatCurrency( txn.amount / 100 ) },
			fee: { value: txn.fee / 100, display: formatCurrency( txn.fee / 100 ) },
			net: { value: ( txn.amount - txn.fee ) / 100, display: formatCurrency( ( txn.amount - txn.fee ) / 100 ) },
			// TODO deposit: { value: available_on * 1000, display: dateI18n( 'Y-m-d H:i', moment( available_on * 1000 ) ) },
			risk_level: outcome && { value: outcome.risk_level, display: capitalize( outcome.risk_level ) },
		};

		return headers.map( ( { key } ) => data[ key ] || { display: null } );
	}

	/**
	 * Returns an array with the summary values to display for the table.
	 *
	 * @returns {Array} List of summary values for the table.
	 */
	summary = () => {
		const { isLoading } = this.props;

		if ( isLoading ) {
			return [];
		}

		return [ {
			label: 'transactions',
			value: this.totalRows(),
		} ];
	};

	/**
	 * Returns the total number of transactions across all pages.
	 *
	 * @returns {Number} The number of transactions.
	 */
	totalRows = () => {
		const { summary } = this.props;

		if ( summary && summary.number_of_transactions ) {
			return summary.number_of_transactions;
		}
		return 0;
	}

	render() {
		const { transactions, query, isLoading } = this.props;

		const transactionsData = transactions.data || [];
		const rows = transactionsData.map( this.transactionsToRows );

		return (
			<TableCard
				title="Transactions"
				isLoading={ isLoading }
				query={ query }
				onQueryChange={ onQueryChange }
				rowsPerPage={ query.per_page }
				totalRows={ this.totalRows() }
				downloadable={ true }
				headers={ headers }
				rows={ rows }
				summary={ this.summary() }
			/>
		);
	}
}

export default withSelect( ( select ) => {
	const {
		showTransactionsPagePlaceholder,
		getTransactionsSummary,
		getTransactionsPage,
	} = select( 'wc-payments-api' );

	// Get the correct query parameters.
	// TODO: figure out how to handle back/forward in browser.
	const tableQuery = () => {
		const query = getQuery();
		if ( ! query ) {
			return {
				paged: 1,
				per_page: 25,
			};
		}

		return {
			paged: query.paged ? query.paged : 1,
			per_page: query.per_page ? query.per_page : 25,
		};
	};

	// Prepare props.
	const { paged, per_page } = tableQuery();
	const isLoading = showTransactionsPagePlaceholder( paged, per_page );
	const transactions = getTransactionsPage( paged, per_page );
	const summary = getTransactionsSummary();

	return {
		isLoading,
		transactions,
		summary,
		query: tableQuery(),
	};
} )( TransactionsList );
