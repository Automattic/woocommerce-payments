/** @format **/

/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import moment from 'moment';
import Currency from '@woocommerce/currency';
import { TableCard } from '@woocommerce/components';
import { onQueryChange, getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies.
 */
import { useTransactions, useTransactionsSummary } from '../data';
import OrderLink from '../components/order-link';
import RiskLevel from '../components/risk-level';
import DetailsLink from '../components/details-link';
import { displayType } from './strings';
import { formatStringValue } from '../util';
import Deposit from './deposit';
import './style.scss';

const currency = new Currency();

// TODO make date / time, amount, fee, and net sortable - when date time is sortable, the background of the info buttons should match
const columns = [
	{ key: 'details', label: '', required: true, cellClassName: 'info-button' },
	{
		key: 'date',
		label: __( 'Date / Time', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
		defaultOrder: 'desc',
		cellClassName: 'date-time',
	},
	{ key: 'type', label: __( 'Type', 'woocommerce-payments' ), required: true },
	{ key: 'amount', label: __( 'Amount', 'woocommerce-payments' ), isNumeric: true },
	{ key: 'fees', label: __( 'Fees', 'woocommerce-payments' ), isNumeric: true },
	{ key: 'net', label: __( 'Net', 'woocommerce-payments' ), isNumeric: true, required: true },
	{ key: 'order', label: __( 'Order #', 'woocommerce-payments' ), required: true },
	{ key: 'source', label: __( 'Source', 'woocommerce-payments' ) },
	{ key: 'customer_name', label: __( 'Customer', 'woocommerce-payments' ) },
	{ key: 'customer_email', label: __( 'Email', 'woocommerce-payments' ), visible: false },
	{ key: 'customer_country', label: __( 'Country', 'woocommerce-payments' ), visible: false },
	{ key: 'risk_level', label: __( 'Risk level', 'woocommerce-payments' ), visible: false },
];
const depositColumn = { key: 'deposit', label: __( 'Deposit', 'woocommerce-payments' ), cellClassName: 'deposit' };

export const TransactionsList = ( props ) => {
	const { transactions, isLoading } = useTransactions( getQuery(), props.depositId );
	const { transactionsSummary, isLoading: isSummaryLoading } = useTransactionsSummary( props.depositId );

	const columnsToDisplay = props.depositId ? columns : [ ...columns, depositColumn ];

	const rows = transactions.map( ( txn ) => {
		const detailsLink = <DetailsLink id={ txn.charge_id } parentSegment="transactions" />;
		const orderUrl = <OrderLink order={ txn.order } />;
		const riskLevel = <RiskLevel risk={ txn.risk_level } />;
		const deposit = <Deposit depositId={ txn.deposit_id } dateAvailable={ txn.date_available } />;

		// Map transaction into table row.
		const data = {
			details: { value: txn.transaction_id, display: detailsLink },
			date: { value: txn.date, display: dateI18n( 'M j, Y / g:iA', moment.utc( txn.date ).local() ) },
			type: { value: txn.type, display: displayType[ txn.type ] || formatStringValue( txn.type ) },
			source: {
				value: txn.source,
				display: <span className={ `payment-method__brand payment-method__brand--${ txn.source }` } />,
			},
			order: { value: txn.order_id, display: orderUrl },
			// eslint-disable-next-line camelcase
			customer_name: { value: txn.customer_name, display: txn.customer_name },
			// eslint-disable-next-line camelcase
			customer_email: { value: txn.customer_email, display: txn.customer_email },
			// eslint-disable-next-line camelcase
			customer_country: { value: txn.customer_country, display: txn.customer_country },
			amount: { value: txn.amount / 100, display: currency.formatCurrency( txn.amount / 100 ) },
			// fees should display as negative. The format $-9.99 is determined by WC-Admin
			fees: { value: txn.fees / 100, display: currency.formatCurrency( ( txn.fees / 100 ) * -1 ) },
			net: { value: txn.net / 100, display: currency.formatCurrency( txn.net / 100 ) },
			// eslint-disable-next-line camelcase
			risk_level: { value: txn.risk_level, display: riskLevel },
			deposit: { value: txn.deposit_id, display: deposit },
		};

		return columnsToDisplay.map( ( { key } ) => data[ key ] || { display: null } );
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
			title={ props.depositId
				? __( 'Deposit transactions', 'woocommerce-payments' )
				: __( 'Transactions', 'woocommerce-payments' )
			}
			isLoading={ isLoading }
			rowsPerPage={ getQuery().per_page || 25 }
			totalRows={ transactionsSummary.count || 0 }
			headers={ columnsToDisplay }
			rows={ rows }
			summary={ isSummaryLoading ? null : summary }
			query={ getQuery() }
			onQueryChange={ onQueryChange }
		/>
	);
};

export default TransactionsList;
