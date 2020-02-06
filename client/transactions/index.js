/** @format **/

/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import moment from 'moment';
import Currency from '@woocommerce/currency';
import { TableCard, Link } from '@woocommerce/components';
import { capitalize } from 'lodash';
import Gridicon from 'gridicons';
import { addQueryArgs } from '@wordpress/url';
import { onQueryChange, getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies.
 */
import { useTransactions, useTransactionsSummary } from '../data';
import OrderLink from '../components/order-link';
import RiskLevel from '../components/risk-level';
import './style.scss';

const currency = new Currency();

// TODO make date / time, amount, fee, and net sortable - when date time is sortable, the background of the info buttons should match
const headers = [
	{ key: 'details', label: '', required: true, cellClassName: 'info-button' },
	{ key: 'created', label: 'Date / Time', required: true, isLeftAligned: true, defaultOrder: 'desc', cellClassName: 'date-time' },
	{ key: 'type', label: 'Type', required: true },
	{ key: 'amount', label: 'Amount', isNumeric: true },
	{ key: 'fee', label: 'Fees', isNumeric: true },
	{ key: 'net', label: 'Net', isNumeric: true, required: true },
	{ key: 'order', label: 'Order #', required: true },
	{ key: 'source', label: 'Source' },
	{ key: 'customer', label: 'Customer' },
	{ key: 'email', label: 'Email', hiddenByDefault: true },
	{ key: 'country', label: 'Country', hiddenByDefault: true },
	// TODO { key: 'deposit', label: 'Deposit', required: true },
	{ key: 'riskLevel', label: 'Risk Level', hiddenByDefault: true },
];

export const TransactionsList = () => {
	const { transactions, isLoading } = useTransactions( getQuery() );
	const { transactionsSummary, isLoading: isSummaryLoading } = useTransactionsSummary();

	const rows = transactions.map( ( txn ) => {
		const orderUrl = <OrderLink order={ txn.order } />;
		// TODO: come up with a link generator utility (woocommerce-payments#229)
		const detailsUrl = addQueryArgs(
			'admin.php',
			{
				page: 'wc-admin',
				path: '/payments/transactions/details',
				id: txn.charge_id,
			}
		);
		const detailsLink = txn.charge_id ? (
			<Link className="transactions-list__details-button" href={ detailsUrl } >
				<Gridicon icon="info-outline" size={ 18 } />
			</Link>
		) : '';

		const riskLevel = <RiskLevel risk={ txn.risk_level } />;

		// Map transaction into table row.
		const data = {
			created: { value: txn.date, display: dateI18n( 'M j, Y / g:iA', moment.utc( txn.date ).local() ) },
			type: { value: txn.type, display: capitalize( txn.type ) },
			source: {
				value: txn.source,
				display: <span className={ `payment-method__brand payment-method__brand--${ txn.source }` }></span>,
			},
			order: { value: txn.order_id, display: orderUrl },
			customer: { value: txn.customer_name, display: txn.customer_name },
			email: { value: txn.customer_email, display: txn.customer_email },
			country: { value: txn.customer_country, display: txn.customer_country },
			amount: { value: txn.amount / 100, display: currency.formatCurrency( txn.amount / 100 ) },
			// fees should display as negative. The format $-9.99 is determined by WC-Admin
			fee: { value: txn.fees / 100, display: currency.formatCurrency( ( txn.fees / 100 ) * -1 ) },
			net: { value: txn.net / 100, display: currency.formatCurrency( txn.net / 100 ) },
			// TODO deposit: { value: available_on * 1000, display: dateI18n( 'Y-m-d H:i', moment( available_on * 1000 ) ) },
			riskLevel: { value: txn.risk_level, display: riskLevel },
			details: { value: txn.transaction_id, display: detailsLink },
		};

		return headers.map( ( { key } ) => data[ key ] || { display: null } );
	} );

	const summary = [
		{ label: 'transactions', value: `${ transactionsSummary.count }` },
		{ label: 'total', value: `${ currency.formatCurrency( transactionsSummary.total / 100 ) }` },
		{ label: 'fees', value: `${ currency.formatCurrency( transactionsSummary.fees / 100 ) }` },
		{ label: 'net', value: `${ currency.formatCurrency( transactionsSummary.net / 100 ) }` },
	];

	return (
		<TableCard
			className="transactions-list"
			title={ __( 'Transactions', 'woocommerce-payments' ) }
			isLoading={ isLoading }
			rowsPerPage={ getQuery().per_page || 25 }
			totalRows={ transactionsSummary.count || 0 }
			headers={ headers }
			rows={ rows }
			summary={ isSummaryLoading ? null : summary }
			query={ getQuery() }
			onQueryChange={ onQueryChange }
		/>
	);
};

export default TransactionsList;
