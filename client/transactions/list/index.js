/** @format **/

/**
 * External dependencies
 */
import { uniq } from 'lodash';
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import moment from 'moment';
import Currency from '@woocommerce/currency';
import { TableCard, Search } from '@woocommerce/components';
import { onQueryChange, getQuery, getSearchWords, updateQueryString } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { useTransactions, useTransactionsSummary } from 'data';
import OrderLink from 'components/order-link';
import RiskLevel from 'components/risk-level';
import ClickableCell from 'components/clickable-cell';
import DetailsLink, { getDetailsURL } from 'components/details-link';
import { displayType } from 'transactions/strings';
import { formatStringValue } from '../../util';
import Deposit from './deposit';
import autocompleter from 'transactions/autocompleter';
import './style.scss';

const currency = new Currency();

const columns = [
	{ key: 'details', label: '', required: true },
	{
		key: 'date',
		label: __( 'Date / Time', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Date and time', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
		defaultOrder: 'desc',
		cellClassName: 'date-time',
		isSortable: true,
		defaultSort: true,
	},
	{
		key: 'type',
		label: __( 'Type', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Type', 'woocommerce-payments' ),
		required: true,
	},
	{
		key: 'amount',
		label: __( 'Amount', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Amount', 'woocommerce-payments' ),
		isNumeric: true,
		isSortable: true,
	},
	{
		key: 'fees',
		label: __( 'Fees', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Fees', 'woocommerce-payments' ),
		isNumeric: true,
		isSortable: true,
	},
	{
		key: 'net',
		label: __( 'Net', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Net', 'woocommerce-payments' ),
		isNumeric: true,
		required: true,
		isSortable: true,
	},
	{
		key: 'order',
		label: __( 'Order #', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Order number', 'woocommerce-payments' ),
		required: true,
	},
	{
		key: 'source',
		label: __( 'Source', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Source', 'woocommerce-payments' ),
	},
	{
		key: 'customer_name',
		label: __( 'Customer', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Customer', 'woocommerce-payments' ),
	},
	{
		key: 'customer_email',
		label: __( 'Email', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Email', 'woocommerce-payments' ),
		visible: false,
	},
	{
		key: 'customer_country',
		label: __( 'Country', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Country', 'woocommerce-payments' ),
		visible: false,
	},
	{
		key: 'risk_level',
		label: __( 'Risk level', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Risk level', 'woocommerce-payments' ),
		visible: false,
	},
];
const depositColumn = {
	key: 'deposit',
	label: __( 'Deposit', 'woocommerce-payments' ),
	screenReaderLabel: __( 'Deposit', 'woocommerce-payments' ),
	cellClassName: 'deposit',
};

export const TransactionsList = ( props ) => {
	const { transactions, isLoading } = useTransactions(
		getQuery(),
		props.depositId
	);
	const {
		transactionsSummary,
		isLoading: isSummaryLoading,
	} = useTransactionsSummary( getQuery(), props.depositId );

	const columnsToDisplay = props.depositId
		? columns
		: [ ...columns, depositColumn ];

	// match background of details and date when sorting
	const detailsColumn =
		columnsToDisplay.find( ( el ) => 'details' === el.key ) || {};
	if ( ! getQuery().orderby || 'date' === getQuery().orderby ) {
		detailsColumn.cellClassName = 'info-button is-sorted';
	} else {
		detailsColumn.cellClassName = 'info-button';
	}

	const rows = transactions.map( ( txn ) => {
		const detailsURL = getDetailsURL( txn.charge_id, 'transactions' );
		const clickable = ( children ) => (
			<ClickableCell href={ detailsURL }>{ children }</ClickableCell>
		);
		const detailsLink = (
			<DetailsLink id={ txn.charge_id } parentSegment="transactions" />
		);
		const orderUrl = <OrderLink order={ txn.order } />;
		const riskLevel = <RiskLevel risk={ txn.risk_level } />;
		const deposit = (
			<Deposit
				depositId={ txn.deposit_id }
				dateAvailable={ txn.date_available }
			/>
		);

		// Map transaction into table row.
		const data = {
			details: { value: txn.transaction_id, display: detailsLink },
			date: {
				value: txn.date,
				display: clickable(
					dateI18n( 'M j, Y / g:iA', moment.utc( txn.date ).local() )
				),
			},
			type: {
				value: txn.type,
				display: clickable(
					displayType[ txn.type ] || formatStringValue( txn.type )
				),
			},
			source: {
				value: txn.source,
				display: clickable(
					<span
						className={ `payment-method__brand payment-method__brand--${ txn.source }` }
					/>
				),
			},
			order: { value: txn.order_id, display: orderUrl },
			// eslint-disable-next-line camelcase
			customer_name: {
				value: txn.customer_name,
				display: clickable( txn.customer_name ),
			},
			// eslint-disable-next-line camelcase
			customer_email: {
				value: txn.customer_email,
				display: clickable( txn.customer_email ),
			},
			// eslint-disable-next-line camelcase
			customer_country: {
				value: txn.customer_country,
				display: clickable( txn.customer_country ),
			},
			amount: {
				value: txn.amount / 100,
				display: clickable(
					currency.formatCurrency( txn.amount / 100 )
				),
			},
			// fees should display as negative. The format $-9.99 is determined by WC-Admin
			fees: {
				value: txn.fees / 100,
				display: clickable(
					currency.formatCurrency( ( txn.fees / 100 ) * -1 )
				),
			},
			net: {
				value: txn.net / 100,
				display: clickable( currency.formatCurrency( txn.net / 100 ) ),
			},
			// eslint-disable-next-line camelcase
			risk_level: {
				value: txn.risk_level,
				display: clickable( riskLevel ),
			},
			deposit: { value: txn.deposit_id, display: deposit },
		};

		return columnsToDisplay.map(
			( { key } ) => data[ key ] || { display: null }
		);
	} );

	const summary = [
		{ label: 'transactions', value: `${ transactionsSummary.count }` },
		{
			label: 'total',
			value: `${ currency.formatCurrency(
				transactionsSummary.total / 100
			) }`,
		},
		{
			label: 'fees',
			value: `${ currency.formatCurrency(
				transactionsSummary.fees / 100
			) }`,
		},
		{
			label: 'net',
			value: `${ currency.formatCurrency(
				transactionsSummary.net / 100
			) }`,
		},
	];

	const searchWords = getSearchWords( getQuery() );
	const searchedLabels = searchWords.map( ( v ) => ( {
		key: v,
		label: v,
	} ) );

	const onSearchChange = ( values ) => {
		// A comma is used as a separator between search terms, so we want to escape any comma they contain.
		const labels = values.map( ( v ) => v.label.replace( ',', '%2C' ) );
		updateQueryString( {
			search: labels.length ? uniq( labels ).join( ',' ) : undefined,
		} );
	};

	return (
		<TableCard
			className="transactions-list woocommerce-report-table has-search"
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
			actions={ [
				<Search
					allowFreeTextSearch={ true }
					inlineTags
					key="search"
					onChange={ onSearchChange }
					placeholder={
						__( 'Search by order number, customer name, or billing email', 'woocommerce-payments' )
					}
					selected={ searchedLabels }
					showClearButton={ true }
					type="custom"
					autocompleter={ autocompleter }
				/>,
			] }
		/>
	);
};

export default TransactionsList;
