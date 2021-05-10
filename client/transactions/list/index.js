/** @format **/

/**
 * External dependencies
 */
import { uniq } from 'lodash';
import { useMemo } from '@wordpress/element';
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import moment from 'moment';
import { TableCard, Search } from '@woocommerce/components';
import { Button } from '@wordpress/components';
import {
	onQueryChange,
	getQuery,
	updateQueryString,
} from '@woocommerce/navigation';
import {
	downloadCSVFile,
	generateCSVDataFromTable,
	generateCSVFileName,
} from '@woocommerce/csv-export';
import Gridicon from 'gridicons';

/**
 * Internal dependencies
 */
import { useTransactions, useTransactionsSummary } from 'data';
import OrderLink from 'components/order-link';
import RiskLevel, { calculateRiskMapping } from 'components/risk-level';
import ClickableCell from 'components/clickable-cell';
import DetailsLink, { getDetailsURL } from 'components/details-link';
import { displayType } from 'transactions/strings';
import { formatStringValue } from 'utils';
import { formatCurrency } from 'utils/currency';
import Deposit from './deposit';
import ConvertedAmount from './converted-amount';
import autocompleter from 'transactions/autocompleter';
import './style.scss';
import TransactionsFilters from '../filters';
import Page from '../../components/page';
import wcpayTracks from 'tracks';

const getColumns = ( includeDeposit, includeSubscription, sortByDate ) =>
	[
		{
			key: 'details',
			label: '',
			required: true,
			// Match background of details and date when sorting.
			cellClassName: 'info-button ' + ( sortByDate ? 'is-sorted' : '' ),
		},
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
		includeSubscription && {
			key: 'subscriptions',
			label: __( 'Subscription #', 'woocommerce-payments' ),
			screenReaderLabel: __(
				'Subscription number',
				'woocommerce-payments'
			),
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
		includeDeposit && {
			key: 'deposit',
			label: __( 'Deposit', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Deposit', 'woocommerce-payments' ),
			cellClassName: 'deposit',
		},
	].filter( Boolean );

export const TransactionsList = ( props ) => {
	const { transactions, isLoading } = useTransactions(
		getQuery(),
		props.depositId
	);
	const {
		transactionsSummary,
		isLoading: isSummaryLoading,
	} = useTransactionsSummary( getQuery(), props.depositId );

	const columnsArgs = [
		! props.depositId,
		wcpaySettings.isSubscriptionsActive,
		! getQuery().orderby || 'date' === getQuery().orderby,
	];
	const columnsToDisplay = useMemo(
		() => getColumns( ...columnsArgs ),
		columnsArgs
	);

	const rows = transactions.map( ( txn ) => {
		const detailsURL = getDetailsURL( txn.charge_id, 'transactions' );
		const clickable = ( children ) => (
			<ClickableCell href={ detailsURL }>{ children }</ClickableCell>
		);
		const detailsLink = (
			<DetailsLink id={ txn.charge_id } parentSegment="transactions" />
		);
		const orderUrl = <OrderLink order={ txn.order } />;
		const orderSubscriptions = txn.order && txn.order.subscriptions;
		const subscriptionsValue =
			wcpaySettings.isSubscriptionsActive && orderSubscriptions
				? orderSubscriptions
						.map( ( subscription ) => subscription.number )
						.join( ', ' )
				: '';
		const subscriptions =
			wcpaySettings.isSubscriptionsActive && orderSubscriptions
				? orderSubscriptions.map( ( subscription, i, all ) => [
						<OrderLink key={ i } order={ subscription } />,
						i !== all.length - 1 && ', ',
				  ] )
				: [];
		const riskLevel = <RiskLevel risk={ txn.risk_level } />;

		const customerName = txn.order ? (
			<a href={ txn.order.customer_url }>{ txn.customer_name }</a>
		) : (
			txn.customer_name
		);
		const customerEmail = txn.order ? (
			<a href={ txn.order.customer_url }>{ txn.customer_email }</a>
		) : (
			txn.customer_email
		);

		const deposit = (
			<Deposit
				depositId={ txn.deposit_id }
				dateAvailable={ txn.date_available }
			/>
		);
		const currency = txn.currency.toUpperCase();

		// Map transaction into table row.
		const data = {
			details: { value: txn.transaction_id, display: detailsLink },
			date: {
				value: txn.date,
				display: clickable(
					dateI18n(
						'M j, Y / g:iA',
						moment.utc( txn.date ).local().toISOString()
					)
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
			order: {
				value: txn.order && txn.order.number,
				display: orderUrl,
			},
			subscriptions: {
				value: subscriptionsValue,
				display: subscriptions,
			},
			// eslint-disable-next-line camelcase
			customer_name: {
				value: txn.customer_name,
				display: customerName,
			},
			// eslint-disable-next-line camelcase
			customer_email: {
				value: txn.customer_email,
				display: customerEmail,
			},
			// eslint-disable-next-line camelcase
			customer_country: {
				value: txn.customer_country,
				display: clickable( txn.customer_country ),
			},
			amount: {
				value: txn.amount / 100,
				display: clickable(
					<ConvertedAmount
						amount={ txn.amount }
						currency={ currency }
						fromAmount={ txn.customer_amount }
						fromCurrency={ txn.customer_currency.toUpperCase() }
					/>
				),
			},
			// fees should display as negative. The format $-9.99 is determined by WC-Admin
			fees: {
				value: txn.fees / 100,
				display: clickable( formatCurrency( txn.fees * -1, currency ) ),
			},
			net: {
				value: txn.net / 100,
				display: clickable( formatCurrency( txn.net, currency ) ),
			},
			// eslint-disable-next-line camelcase
			risk_level: {
				value: calculateRiskMapping( txn.risk_level ),
				display: clickable( riskLevel ),
			},
			deposit: { value: txn.deposit_id, display: deposit },
		};

		return columnsToDisplay.map(
			( { key } ) => data[ key ] || { display: null }
		);
	} );

	const searchedLabels =
		getQuery().search &&
		getQuery().search.map( ( v ) => ( {
			key: v,
			label: v,
		} ) );

	const onSearchChange = ( values ) => {
		updateQueryString( {
			search: values.length
				? uniq( values.map( ( v ) => v.label ) )
				: undefined,
		} );
	};

	let searchPlaceholder = wcpaySettings.isSubscriptionsActive
		? __(
				'Search by order number, subscription number, customer name, or billing email',
				'woocommerce-payments'
		  )
		: __(
				'Search by order number, customer name, or billing email',
				'woocommerce-payments'
		  );

	const title = props.depositId
		? __( 'Deposit transactions', 'woocommerce-payments' )
		: __( 'Transactions', 'woocommerce-payments' );

	const downloadable = !! rows.length;

	const onDownload = () => {
		const { page, path, ...params } = getQuery();

		downloadCSVFile(
			generateCSVFileName( title, params ),
			generateCSVDataFromTable( columnsToDisplay, rows )
		);

		wcpayTracks.recordEvent( 'wcpay_transactions_download', {
			// eslint-disable-next-line camelcase
			exported_transactions: rows.length,
			// eslint-disable-next-line camelcase
			total_transactions: transactionsSummary.count,
		} );
	};

	if ( ! wcpaySettings.featureFlags.customSearch ) {
		searchPlaceholder = __(
			'Search by customer name',
			'woocommerce-payments'
		);
	}

	// Generate summary based on loading state and available currencies information
	const summary = [
		{
			label: __( 'transactions', 'woocommerce-payments' ),
			value: `${ transactionsSummary.count }`,
		},
	];
	const isCurrencyFiltered = 'string' === typeof getQuery().store_currency_is;
	if ( ! isSummaryLoading ) {
		const isSingleCurrency =
			2 > ( transactionsSummary.store_currencies || [] ).length;
		if ( isSingleCurrency || isCurrencyFiltered ) {
			summary.push(
				{
					label: __( 'total', 'woocommerce-payments' ),
					value: `${ formatCurrency(
						transactionsSummary.total,
						transactionsSummary.currency
					) }`,
				},
				{
					label: __( 'fees', 'woocommerce-payments' ),
					value: `${ formatCurrency(
						transactionsSummary.fees,
						transactionsSummary.currency
					) }`,
				},
				{
					label: __( 'net', 'woocommerce-payments' ),
					value: `${ formatCurrency(
						transactionsSummary.net,
						transactionsSummary.currency
					) }`,
				}
			);
		}
	}

	const showFilters = ! props.depositId;
	const storeCurrencies =
		transactionsSummary.store_currencies ||
		( isCurrencyFiltered ? [ getQuery().store_currency_is ] : [] );

	return (
		<Page>
			{ showFilters && (
				<TransactionsFilters storeCurrencies={ storeCurrencies } />
			) }
			<TableCard
				className="transactions-list woocommerce-report-table has-search"
				title={
					props.depositId
						? __( 'Deposit transactions', 'woocommerce-payments' )
						: __( 'Transactions', 'woocommerce-payments' )
				}
				isLoading={ isLoading }
				rowsPerPage={ getQuery().per_page || 25 }
				totalRows={ transactionsSummary.count || 0 }
				headers={ columnsToDisplay }
				rows={ rows }
				summary={ summary }
				query={ getQuery() }
				onQueryChange={ onQueryChange }
				actions={ [
					<Search
						allowFreeTextSearch={ true }
						inlineTags
						key="search"
						onChange={ onSearchChange }
						placeholder={ searchPlaceholder }
						selected={ searchedLabels }
						showClearButton={ true }
						type={
							wcpaySettings.featureFlags.customSearch
								? 'custom'
								: 'customers'
						}
						autocompleter={ autocompleter }
					/>,
					downloadable && (
						<Button
							key="download"
							className="woocommerce-table__download-button"
							disabled={ isLoading }
							onClick={ onDownload }
						>
							<Gridicon icon={ 'cloud-download' } />
							<span className="woocommerce-table__download-button__label">
								{ __( 'Download', 'woocommerce-payments' ) }
							</span>
						</Button>
					),
				] }
			/>
		</Page>
	);
};

export default TransactionsList;
