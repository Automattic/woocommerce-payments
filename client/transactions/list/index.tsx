/** @format **/

/**
 * External dependencies
 */
import * as React from 'react';
import { uniq } from 'lodash';
import { useMemo } from '@wordpress/element';
import { dateI18n } from '@wordpress/date';
import { __, _n } from '@wordpress/i18n';
import moment from 'moment';
import {
	TableCard,
	Search,
	Link,
	TableCardColumn,
} from '@woocommerce/components';
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
import { useTransactions, useTransactionsSummary } from 'data/index';
import OrderLink from 'components/order-link';
import RiskLevel, { calculateRiskMapping } from 'components/risk-level';
import ClickableCell from 'components/clickable-cell';
import { getDetailsURL } from 'components/details-link';
import { displayType } from 'transactions/strings';
import { formatStringValue } from 'utils';
import { formatCurrency, formatExplicitCurrency } from 'utils/currency';
import Deposit from './deposit';
import ConvertedAmount from './converted-amount';
import autocompleter from 'transactions/autocompleter';
import './style.scss';
import TransactionsFilters from '../filters';
import Page from '../../components/page';
import wcpayTracks from 'tracks';

interface TransactionsListProps {
	depositId?: string;
}

interface Column extends TableCardColumn {
	key:
		| 'transaction_id'
		| 'date'
		| 'type'
		| 'amount'
		| 'fees'
		| 'net'
		| 'order'
		| 'subscriptions'
		| 'source'
		| 'customer_name'
		| 'customer_email'
		| 'customer_country'
		| 'risk_level'
		| 'deposit';
	visible?: boolean;
	cellClassName?: string;
}

const getColumns = (
	includeDeposit: boolean,
	includeSubscription: boolean
): Column[] =>
	[
		{
			key: 'transaction_id',
			label: __( 'Transaction Id', 'woocommerce-payments' ),
			visible: false,
			isLeftAligned: true,
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
			isLeftAligned: true,
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
			cellClassName: 'is-center-aligned',
		},
		{
			key: 'customer_name',
			label: __( 'Customer', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Customer', 'woocommerce-payments' ),
			isLeftAligned: true,
		},
		{
			key: 'customer_email',
			label: __( 'Email', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Email', 'woocommerce-payments' ),
			visible: false,
			isLeftAligned: true,
		},
		{
			key: 'customer_country',
			label: __( 'Country', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Country', 'woocommerce-payments' ),
			visible: false,
			isLeftAligned: true,
		},
		{
			key: 'risk_level',
			label: __( 'Risk level', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Risk level', 'woocommerce-payments' ),
			visible: false,
			isLeftAligned: true,
		},
		includeDeposit && {
			key: 'deposit',
			label: __( 'Deposit', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Deposit', 'woocommerce-payments' ),
			cellClassName: 'deposit',
			isLeftAligned: true,
		},
	].filter( Boolean ) as Column[]; // We explicitly define the type because TypeScript can't infer the type post-filtering.

export const TransactionsList = (
	props: TransactionsListProps
): JSX.Element => {
	const { transactions, isLoading } = useTransactions(
		getQuery(),
		props.depositId ?? ''
	);
	const {
		transactionsSummary,
		isLoading: isSummaryLoading,
	} = useTransactionsSummary( getQuery(), props.depositId ?? '' );

	const columnsToDisplay = useMemo(
		() =>
			getColumns(
				! props.depositId,
				wcpaySettings.isSubscriptionsActive
			),
		[ props.depositId ]
	);

	const rows = transactions.map( ( txn ) => {
		const detailsURL = getDetailsURL( txn.charge_id, 'transactions' );
		const clickable = ( children: JSX.Element | string ) => (
			<ClickableCell href={ detailsURL }>{ children }</ClickableCell>
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
			<Link href={ txn.order.customer_url ?? '' }>
				{ txn.customer_name }
			</Link>
		) : (
			txn.customer_name
		);
		const customerEmail = txn.order ? (
			<Link href={ txn.order.customer_url ?? '' }>
				{ txn.customer_email }
			</Link>
		) : (
			txn.customer_email
		);

		const deposit = (
			<Deposit
				depositId={ txn.deposit_id ?? '' }
				dateAvailable={ txn.available_on }
			/>
		);
		const currency = txn.currency.toUpperCase();

		// Map transaction into table row.
		const data = {
			transaction_id: {
				value: txn.transaction_id,
				display: clickable( txn.transaction_id ),
			},
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
			customer_name: {
				value: txn.customer_name,
				display: customerName,
			},
			customer_email: {
				value: txn.customer_email,
				display: customerEmail,
			},
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
				display: clickable(
					formatExplicitCurrency( txn.net, currency )
				),
			},
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
		getQuery().search?.map( ( v ) => ( {
			key: v,
			label: v,
		} ) );

	const onSearchChange = ( values: Column[] ) => {
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
		// We destructure page and path to get the right params.
		// eslint-disable-next-line @typescript-eslint/no-unused-vars
		const { page, path, ...params } = getQuery();

		downloadCSVFile(
			generateCSVFileName( title, params ),
			generateCSVDataFromTable( columnsToDisplay, rows )
		);

		wcpayTracks.recordEvent( 'wcpay_transactions_download', {
			exported_transactions: rows.length,
			total_transactions: transactionsSummary.count,
		} );
	};

	if ( ! wcpaySettings.featureFlags.customSearch ) {
		searchPlaceholder = __(
			'Search by customer name',
			'woocommerce-payments'
		);
	}

	const isCurrencyFiltered = 'string' === typeof getQuery().store_currency_is;

	const isSingleCurrency =
		2 > ( transactionsSummary.store_currencies || [] ).length;

	// initializing summary with undefined as we don't want to render the TableSummary component unless we have the data
	let summary;
	const isTransactionsSummaryDataLoaded =
		transactionsSummary.count !== undefined &&
		transactionsSummary.total !== undefined &&
		false === isSummaryLoading;

	// Generate summary only if the data has been loaded
	if ( isTransactionsSummaryDataLoaded ) {
		summary = [
			{
				label: _n(
					'transaction',
					'transactions',
					// We've already checked that `.count` is not undefined, but TypeScript doesn't detect
					// that so we remove the `undefined` in the type manually.
					transactionsSummary.count as number,
					'woocommerce-payments'
				),
				value: `${ transactionsSummary.count }`,
			},
		];

		const hasTransactions = ( transactionsSummary.count as number ) > 0;
		if ( hasTransactions && ( isSingleCurrency || isCurrencyFiltered ) ) {
			summary.push(
				{
					label: __( 'total', 'woocommerce-payments' ),
					value: `${ formatExplicitCurrency(
						// We've already checked that `.total` is not undefined, but TypeScript doesn't detect
						// that so we remove the `undefined` in the type manually.
						transactionsSummary.total as number,
						transactionsSummary.currency
					) }`,
				},
				{
					label: __( 'fees', 'woocommerce-payments' ),
					value: `${ formatCurrency(
						transactionsSummary.fees ?? 0,
						transactionsSummary.currency
					) }`,
				},
				{
					label: __( 'net', 'woocommerce-payments' ),
					value: `${ formatExplicitCurrency(
						transactionsSummary.net ?? 0,
						transactionsSummary.currency
					) }`,
				}
			);
		}
	}

	const showFilters = ! props.depositId;
	const storeCurrencies =
		transactionsSummary.store_currencies ||
		( isCurrencyFiltered ? [ getQuery().store_currency_is ?? '' ] : [] );

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
				rowsPerPage={ parseInt( getQuery().per_page ?? '', 10 ) || 25 }
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
