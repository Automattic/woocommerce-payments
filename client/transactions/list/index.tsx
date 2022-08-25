/** @format **/

/**
 * External dependencies
 */
import React, { Fragment, useState } from 'react';
import { uniq } from 'lodash';
import { useDispatch } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { dateI18n } from '@wordpress/date';
import { __, _n, sprintf } from '@wordpress/i18n';
import moment from 'moment';
import {
	TableCard,
	Search,
	Link,
	TableCardColumn,
} from '@woocommerce/components';
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
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { useTransactions, useTransactionsSummary } from 'data/index';
import { Transaction } from 'data/transactions/hooks';
import OrderLink from 'components/order-link';
import RiskLevel, { calculateRiskMapping } from 'components/risk-level';
import ClickableCell from 'components/clickable-cell';
import { getDetailsURL } from 'components/details-link';
import { displayType } from 'transactions/strings';
import { displayStatus as displayDepositStatus } from 'deposits/strings';
import { formatStringValue } from 'utils';
import { formatCurrency, formatExplicitCurrency } from 'utils/currency';
import { getChargeChannel } from 'utils/charge';
import Deposit from './deposit';
import ConvertedAmount from './converted-amount';
import autocompleter from 'transactions/autocompleter';
import './style.scss';
import TransactionsFilters from '../filters';
import Page from '../../components/page';
import wcpayTracks from 'tracks';
import DownloadButton from 'components/download-button';
import { getTransactionsCSV } from '../../data/transactions/resolvers';
import p24BankList from '../../payment-details/payment-method/p24/bank-list';
import { applyThousandSeparator } from '../../utils/index.js';

interface TransactionsListProps {
	depositId?: string;
}

interface Column extends TableCardColumn {
	key:
		| 'transaction_id'
		| 'date'
		| 'type'
		| 'channel'
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

const getPaymentSourceDetails = ( txn: Transaction ) => {
	if ( ! txn.source_identifier ) {
		return <Fragment></Fragment>;
	}

	switch ( txn.source ) {
		case 'giropay':
			return <Fragment>{ txn.source_identifier }</Fragment>;
		case 'p24':
			return (
				<Fragment>
					{ p24BankList[ txn.source_identifier ] ?? '' }
				</Fragment>
			);
		default:
			return (
				<Fragment>
					&nbsp;&bull;&bull;&bull;&bull;&nbsp;{ ' ' }
					{ txn.source_identifier }
				</Fragment>
			);
	}
};

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
			key: 'channel',
			label: __( 'Channel', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Channel', 'woocommerce-payments' ),
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
			label: __( 'Deposit date', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Deposit date', 'woocommerce-payments' ),
			cellClassName: 'deposit',
			isLeftAligned: true,
		},
		includeDeposit && {
			key: 'deposit_status',
			label: __( 'Deposit status', 'woocommerce-payments' ),
			visible: false,
		},
	].filter( Boolean ) as Column[]; // We explicitly define the type because TypeScript can't infer the type post-filtering.

export const TransactionsList = (
	props: TransactionsListProps
): JSX.Element => {
	const [ isDownloading, setIsDownloading ] = useState( false );
	const { createNotice } = useDispatch( 'core/notices' );
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

	const totalRows = transactionsSummary.count || 0;
	const rows = transactions.map( ( txn ) => {
		const detailsURL =
			getDetailsURL(
				txn.payment_intent_id || txn.charge_id,
				'transactions'
			) +
			'&transaction_id=' +
			txn.transaction_id +
			'&transaction_type=' +
			( txn.metadata && 'card_reader_fee' === txn.metadata.charge_type
				? txn.metadata.charge_type
				: txn.type );
		const clickable =
			'financing_payout' !== txn.type &&
			! ( 'financing_paydown' === txn.type && '' === txn.charge_id )
				? ( children: JSX.Element | string ) => (
						<ClickableCell href={ detailsURL }>
							{ children }
						</ClickableCell>
				  )
				: ( children: JSX.Element | string ) => children;

		const orderUrl = txn.order ? (
			<OrderLink order={ txn.order } />
		) : (
			__( 'N/A', 'woocommerce-payments' )
		);
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

		const customerName =
			txn.order && txn.order.customer_url ? (
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

		const dataType = txn.metadata ? txn.metadata.charge_type : txn.type;
		const formatAmount = () => {
			const amount = txn.metadata ? 0 : txn.amount;
			const fromAmount = txn.customer_amount ? txn.customer_amount : 0;

			return {
				value: amount / 100,
				display: clickable(
					<ConvertedAmount
						amount={ amount }
						currency={ currency }
						fromAmount={ fromAmount }
						fromCurrency={ txn.customer_currency.toUpperCase() }
					/>
				),
			};
		};
		const formatFees = () => {
			const isCardReader =
				txn.metadata && txn.metadata.charge_type === 'card_reader_fee';
			const feeAmount =
				( isCardReader ? txn.amount : txn.fees * -1 ) / 100;
			return {
				value: feeAmount,
				display: clickable(
					0 !== feeAmount
						? formatCurrency(
								isCardReader ? txn.amount : txn.fees * -1,
								currency
						  )
						: __( 'N/A', 'woocommerce-payments' )
				),
			};
		};

		const depositStatus = txn.deposit_status
			? displayDepositStatus[ txn.deposit_status ]
			: '';

		const isFinancingType =
			-1 !==
			[ 'financing_payout', 'financing_paydown' ].indexOf( txn.type );

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
			channel: {
				value: getChargeChannel( txn.channel ),
				display: clickable( getChargeChannel( txn.channel ) ),
			},
			type: {
				value: displayType[ dataType ],
				display: clickable(
					displayType[ dataType ] || formatStringValue( dataType )
				),
			},
			source: {
				value: txn.source,
				display: ! isFinancingType ? (
					clickable(
						<span className="payment-method-details-list-item">
							<span
								className={ `payment-method__brand payment-method__brand--${ txn.source }` }
							/>
							{ getPaymentSourceDetails( txn ) }
						</span>
					)
				) : (
					<span className={ 'payment-method__brand' }>—</span>
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
				display: ! isFinancingType
					? customerName
					: __( 'N/A', 'woocommerce-payments' ),
			},
			customer_email: {
				value: txn.customer_email,
				display: ! isFinancingType
					? customerEmail
					: __( 'N/A', 'woocommerce-payments' ),
			},
			customer_country: {
				value: txn.customer_country,
				display: clickable( txn.customer_country ),
			},
			amount: formatAmount(),
			// fees should display as negative. The format $-9.99 is determined by WC-Admin
			fees: formatFees(),
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
			deposit: { value: txn.available_on, display: deposit },
			deposit_status: {
				value: depositStatus,
				display: depositStatus,
			},
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

	const onDownload = async () => {
		setIsDownloading( true );

		// We destructure page and path to get the right params.
		// eslint-disable-next-line @typescript-eslint/no-unused-vars
		const { page, path, ...params } = getQuery();
		const downloadType = totalRows > rows.length ? 'endpoint' : 'browser';
		const userEmail = wcpaySettings.currentUserEmail;

		if ( 'endpoint' === downloadType ) {
			const {
				date_after: dateAfter,
				date_before: dateBefore,
				date_between: dateBetween,
				match,
				search,
				type_is: typeIs,
				type_is_not: typeIsNot,
				customer_currency_is: customerCurrencyIs,
				customer_currency_is_not: customerCurrencyIsNot,
			} = params;
			const depositId = props.depositId;

			const isFiltered =
				!! dateAfter ||
				!! dateBefore ||
				!! dateBetween ||
				!! search ||
				!! typeIs ||
				!! typeIsNot;

			const confirmThreshold = 10000;
			const confirmMessage = sprintf(
				__(
					"You are about to export %d transactions. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?",
					'woocommerce-payments'
				),
				totalRows
			);

			if (
				isFiltered ||
				totalRows < confirmThreshold ||
				window.confirm( confirmMessage )
			) {
				try {
					const {
						exported_transactions: exportedTransactions,
					} = await apiFetch( {
						path: getTransactionsCSV( {
							userEmail,
							dateAfter,
							dateBefore,
							dateBetween,
							match,
							search,
							typeIs,
							typeIsNot,
							customerCurrencyIs,
							customerCurrencyIsNot,
							depositId,
						} ),
						method: 'POST',
					} );

					createNotice(
						'success',
						sprintf(
							__(
								'Your export will be emailed to %s',
								'woocommerce-payments'
							),
							userEmail
						)
					);

					wcpayTracks.recordEvent( 'wcpay_transactions_download', {
						exported_transactions: exportedTransactions,
						total_transactions: exportedTransactions,
						download_type: downloadType,
					} );
				} catch {
					createNotice(
						'error',
						__(
							'There was a problem generating your export.',
							'woocommerce-payments'
						)
					);
				}
			}
		} else {
			downloadCSVFile(
				generateCSVFileName( title, params ),
				generateCSVDataFromTable( columnsToDisplay, rows )
			);

			wcpayTracks.recordEvent( 'wcpay_transactions_download', {
				exported_transactions: rows.length,
				total_transactions: transactionsSummary.count,
				download_type: downloadType,
			} );
		}

		setIsDownloading( false );
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
				value: `${ applyThousandSeparator(
					transactionsSummary.count as number
				) }`,
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
	const customerCurrencies = transactionsSummary.customer_currencies || [];

	return (
		<Page>
			{ showFilters && (
				<TransactionsFilters
					storeCurrencies={ storeCurrencies }
					customerCurrencies={ customerCurrencies }
				/>
			) }
			<TableCard
				className="transactions-list woocommerce-report-table has-search"
				title={ title }
				isLoading={ isLoading }
				rowsPerPage={ parseInt( getQuery().per_page ?? '', 10 ) || 25 }
				totalRows={ totalRows }
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
						<DownloadButton
							key="download"
							isDisabled={ isLoading || isDownloading }
							onClick={ onDownload }
						/>
					),
				] }
			/>
		</Page>
	);
};

// Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi tempor urna tortor, et convallis massa dapibus in. Aenean non vestibulum felis. Phasellus iaculis placerat interdum. Sed vitae porta orci, sit amet cursus felis. Ut faucibus lectus sed enim hendrerit, nec feugiat nisl condimentum. Aenean rutrum scelerisque orci, at facilisis arcu viverra porttitor. Phasellus et dictum leo. Sed ornare consequat rhoncus. Morbi quis sagittis neque. Vivamus auctor elementum tempor.

// Nam aliquam a dolor nec viverra. Quisque commodo laoreet rhoncus. Quisque eu diam ante. Ut ac dolor dui. Curabitur ultrices aliquam justo quis dapibus. Nunc eleifend at eros vel molestie. Curabitur ornare, mi non facilisis pellentesque, orci urna varius magna, quis eleifend mi lorem ut velit.

// Donec id tortor justo. Cras eu lacinia odio. Maecenas sit amet est auctor, placerat sapien vehicula, fringilla dui. Aliquam semper venenatis nulla in dapibus. Vivamus ultricies nulla at mauris posuere ullamcorper. Cras suscipit neque sed ipsum lobortis sagittis. Vivamus rhoncus vehicula mi, in tincidunt lorem. Phasellus eget augue fringilla, maximus odio sit amet, sodales elit. Donec auctor in elit ut ultricies. Nam et dui arcu. Fusce in orci eget nibh auctor elementum. Aenean ac vestibulum augue.

// Donec ornare magna eros, sed vehicula ligula tincidunt vitae. Mauris et justo vitae lacus accumsan hendrerit id in velit. Curabitur hendrerit iaculis ex eget feugiat. Sed eu ante facilisis, congue augue tempus, dapibus ante. Sed lobortis quam eu ipsum porttitor, accumsan lobortis nisi sagittis. Suspendisse potenti. Morbi mattis vitae ipsum eu pulvinar. Nullam a arcu pellentesque, vehicula nibh in, elementum magna. Etiam at orci felis.

// Ut sit amet mollis turpis, vel condimentum nisi. Nullam id lectus efficitur, elementum erat quis, rhoncus libero. Morbi sit amet mi consequat, maximus ligula non, ullamcorper elit. Nunc cursus dolor sed malesuada efficitur. Mauris eu arcu congue ex bibendum congue. Sed sodales auctor tortor. Nam augue sem, pulvinar et urna sit amet, tristique feugiat arcu. Ut aliquet vel risus sollicitudin mattis. Donec sit amet interdum urna. Sed feugiat vestibulum urna, in tincidunt magna lacinia id. Etiam et sapien quis felis commodo pulvinar. Duis viverra in libero vitae pretium. Nunc aliquam gravida neque, sit amet commodo magna tincidunt eget. Nullam id metus rhoncus, rhoncus ante a, hendrerit quam.

// Phasellus finibus iaculis sagittis. Aliquam malesuada fermentum massa, eget condimentum mi tincidunt eget. Nam mi elit, molestie id tincidunt non, efficitur ut mauris. Pellentesque sagittis orci id nisi consequat finibus. Suspendisse egestas fringilla mi, consectetur porttitor erat cursus nec. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Sed in quam sit amet orci dignissim molestie. Quisque semper libero quis maximus faucibus. In laoreet quam eu nisl molestie, sed pharetra diam suscipit. Aliquam et bibendum magna. Morbi accumsan ut tortor vel luctus. Morbi eu accumsan ligula, eu condimentum leo. Etiam pretium lacus eros, ut malesuada nunc mollis vel.

// Morbi id iaculis massa. Morbi lobortis dui est, quis efficitur augue porta tristique. Donec commodo nisl eu enim tincidunt lacinia. Praesent tincidunt augue mi, nec porttitor libero consectetur vitae. Nam tellus lectus, cursus eget euismod a, feugiat in tortor. Aenean eget sodales lorem. Curabitur et eleifend massa, id elementum ante. Sed aliquam vehicula sem, sed luctus sem auctor et. Fusce vestibulum, enim ac ullamcorper viverra, lectus elit iaculis eros, consequat sollicitudin lorem dolor nec lacus. Vivamus cursus lobortis felis eu pulvinar. Vestibulum ac velit at nunc faucibus interdum. Praesent nulla nisl, cursus et turpis ac, pellentesque feugiat justo. Praesent malesuada ultricies porta. Etiam mattis arcu ut libero pharetra sagittis. Quisque pharetra et dui ut lacinia.

// Duis et dictum dolor. Donec sollicitudin augue sit amet diam condimentum, non volutpat felis lobortis. Proin congue id nibh ultricies malesuada. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; Sed neque mi, volutpat id orci id, imperdiet vestibulum nisl. Nullam laoreet mauris mauris, vel facilisis metus posuere et. Nunc in porttitor enim, sollicitudin fermentum libero. Duis auctor ac elit ac vehicula. Fusce erat sem, finibus in ultrices eu, pulvinar vitae erat. Curabitur sit amet metus scelerisque, ultrices magna eu, finibus nisl. Sed ut magna suscipit, ultrices dui at, porttitor nisl. Phasellus tempus bibendum erat in iaculis.

// Duis sit amet ante et quam pretium eleifend eu id risus. Nam eu molestie dui, sed vehicula tortor. Interdum et malesuada fames ac ante ipsum primis in faucibus. Nam eros dolor, elementum tristique sapien sed, consectetur cursus sapien. Praesent ut sollicitudin elit, eu cursus mauris. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Morbi faucibus dapibus lorem, quis finibus ante luctus sit amet. Donec eleifend nisl blandit posuere cursus. Suspendisse consectetur sit amet arcu a mattis.

// Fusce quam elit, gravida at orci et, accumsan maximus sapien. Vestibulum tristique rhoncus turpis, vitae tempus nisl aliquam id. Integer porttitor turpis diam, quis pretium urna euismod sit amet. Quisque volutpat euismod magna, lobortis accumsan nulla vehicula sed. Maecenas tristique eros ante, at vulputate enim bibendum laoreet. Nullam ut suscipit risus, vel euismod enim. Sed gravida volutpat sapien, vitae condimentum dolor euismod a. Suspendisse rhoncus eros vitae odio posuere ultrices. Quisque ac efficitur felis, pellentesque dapibus arcu. Sed fermentum felis ut pulvinar scelerisque.

// Nullam lobortis consectetur sodales. Nam id scelerisque dui. Interdum et malesuada fames ac ante ipsum primis in faucibus. Maecenas aliquam tristique nulla, a dignissim nulla faucibus malesuada. In sodales erat vel arcu dapibus pellentesque. Proin molestie eu lectus nec ultrices. Sed at ultrices nulla, non faucibus nisi. Donec ullamcorper consectetur sapien nec scelerisque. Vestibulum eu nisl dignissim, faucibus elit sed, pharetra augue. Aliquam ac ullamcorper libero, a aliquam velit.

// Sed vitae imperdiet augue. Aliquam interdum felis ut mattis fermentum. Aenean tempus, purus eu aliquet imperdiet, erat purus tempor eros, eget condimentum dolor est eget magna. Nullam in maximus eros, sit amet lacinia massa. Mauris vitae volutpat ex, ut egestas leo. Phasellus congue scelerisque neque, eu interdum sem laoreet a. Nullam in lectus et mi scelerisque ullamcorper. Suspendisse placerat pulvinar neque, vel tempor lectus sodales eget. Sed lobortis magna non nisl bibendum, nec pulvinar eros convallis.

// Aliquam erat volutpat. Cras fermentum suscipit elit in sodales. Donec ut rhoncus est, vitae luctus lorem. Quisque consequat neque eu aliquam pretium. In sit amet tellus neque. Nulla congue metus erat, quis consequat odio accumsan et. Sed accumsan suscipit ex. In varius erat massa, id aliquet enim convallis eleifend. Phasellus posuere convallis consectetur. Nunc vestibulum mi et massa varius laoreet. Aenean augue diam, ornare a purus a, placerat mattis orci. Quisque efficitur tristique euismod. Aliquam suscipit magna venenatis, auctor nibh quis, eleifend ante. In interdum ultrices augue, eu condimentum neque suscipit consectetur. Curabitur auctor, erat ut consectetur congue, nibh ipsum ultrices risus, sed bibendum sapien purus a libero. Vestibulum dui leo, molestie id lobortis nec, maximus et felis.

// Aenean eget pellentesque urna. Nullam ac finibus tellus, at viverra ligula. Sed eu velit nec tortor tempor varius quis id nisi. Curabitur scelerisque placerat neque, sit amet pharetra tortor gravida non. Phasellus pretium tristique diam, id hendrerit felis euismod vel. Vivamus ultricies sem quis quam pellentesque, eget aliquam justo lobortis. Phasellus lacinia lacus eget ligula molestie, vitae efficitur odio fringilla.

// Maecenas fermentum neque sit amet libero pretium, nec mollis nibh viverra. Nullam convallis feugiat arcu. Maecenas at orci eget tellus ultricies euismod. Mauris porttitor tortor non ex varius tristique. Morbi et commodo diam, sit amet ultricies libero. Suspendisse consectetur, ipsum eu pellentesque tempor, dui lacus mattis risus, eget vulputate nisl tortor nec sem. Fusce tincidunt enim varius tempus vehicula.

// Morbi volutpat libero id ex condimentum, vitae pretium risus aliquet. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut bibendum congue lacus, sed pulvinar leo eleifend ut. Sed feugiat ipsum malesuada leo lobortis, at cursus tortor porttitor. Fusce fringilla a tortor ac interdum. Proin volutpat tortor ante, in interdum tellus euismod a. Duis vitae sagittis sapien, ac rutrum lectus.

// Pellentesque augue diam, euismod sit amet auctor eu, fringilla vel mi. Nulla in mollis urna. Cras nec massa eu lectus malesuada elementum. Ut vitae tristique risus. Donec eget bibendum augue. Ut condimentum nunc sem, eu pretium tellus convallis sit amet. Etiam a commodo orci. Integer rutrum velit mi, dapibus fermentum diam egestas ut. Donec ac sem tempus, lobortis lorem at, viverra lectus. Aliquam placerat bibendum magna non consectetur. Sed eu quam vitae felis mattis laoreet vel eu nisl. Nullam semper pulvinar tortor vitae mollis. Aliquam mollis justo lorem, at convallis enim fermentum sit amet.

// Maecenas nulla purus, gravida a nunc ac, gravida vulputate ligula. Vivamus feugiat vitae erat id lacinia. Morbi eu condimentum massa. Fusce luctus libero non elit vehicula imperdiet sit amet vel nunc. Nunc placerat, augue a imperdiet luctus, lorem felis dictum mi, nec rhoncus mi ligula vel justo. Nam tempus volutpat leo, in vulputate tortor semper non. Fusce sodales nisl non nisl auctor placerat.

// Suspendisse mollis consectetur accumsan. Maecenas porttitor ligula in massa finibus aliquam. Praesent at commodo metus. Praesent consectetur fermentum lorem sed facilisis. Aenean eu luctus sapien. Cras efficitur dictum leo, ac pellentesque lacus mollis sed. Mauris aliquet porttitor ligula, sed tristique enim volutpat sit amet.

// Phasellus feugiat dolor quam, quis volutpat lacus sodales ac. Proin consectetur nisl eu purus volutpat condimentum. Sed sodales ullamcorper augue a ornare. Mauris a dignissim diam. Mauris eu turpis commodo, aliquam leo nec, vehicula odio. Nullam ac varius leo. Nulla tempor, dolor in iaculis aliquet, turpis neque aliquam sem, sit amet semper arcu neque vel nulla. Mauris in metus sed libero elementum sodales ac eget risus. Duis rhoncus, elit et luctus tempor, ante leo malesuada tellus, luctus tempus ligula est fringilla dolor. Sed elementum, ipsum ac pellentesque mollis, sapien ipsum consequat lacus, id condimentum est lacus id sapien. In magna urna, eleifend sit amet posuere at, cursus in tellus.

// Nulla pellentesque augue ex. Proin ut ipsum libero. Phasellus tristique risus in ipsum pharetra, in rhoncus quam mattis. Cras ultricies ipsum eget turpis porttitor, a sodales augue dignissim. Praesent ac leo sem. Sed enim sem, dapibus in odio quis, aliquam dictum quam. Phasellus ullamcorper urna ac tincidunt placerat. Praesent a elit enim. Ut ut lectus non nulla ullamcorper viverra ac sed magna. Cras pretium ante eu tellus laoreet, quis aliquet leo feugiat. Suspendisse non magna a nisl bibendum aliquet vel at nisi. Sed faucibus, lectus ut eleifend feugiat, felis mi semper augue, eu facilisis ex dolor in dui. Donec ipsum massa, convallis et turpis vel, pharetra faucibus ex. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Donec id velit a augue porta malesuada. Maecenas nec pretium orci.

// Proin faucibus ullamcorper laoreet. Quisque felis tellus, bibendum ut diam a, gravida luctus nibh. Aliquam erat volutpat. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Donec ac faucibus augue. Nam quis convallis ante. Vestibulum gravida sem consequat cursus suscipit. Nullam efficitur tincidunt enim, quis condimentum odio maximus eu. Etiam pretium sapien eget gravida posuere. Vivamus scelerisque eros sed dictum mollis. Nullam euismod nunc eros, fermentum faucibus risus euismod ac. Ut non viverra ligula, in eleifend felis. Phasellus sollicitudin, nisl sit amet ornare imperdiet, justo est commodo elit, interdum dignissim velit dui quis enim. Aliquam a sapien vitae turpis tempor imperdiet eu in ligula.

// Proin nec massa nec augue aliquam vestibulum. Morbi sagittis sit amet purus et vehicula. Maecenas feugiat mollis justo, vel fringilla ipsum. Nunc nec porta diam. Aenean accumsan lobortis leo, eget volutpat felis tincidunt eget. Nullam a est enim. Sed in neque id augue tempor varius at a dolor. Donec venenatis purus eu eros faucibus posuere. Fusce tellus lorem, feugiat eu tempor eget, interdum quis elit. Fusce venenatis nulla eget dapibus dapibus. Vestibulum quis dui elementum, gravida dolor a, consequat augue. In id arcu et odio hendrerit suscipit eget vel metus. Maecenas convallis vestibulum purus eget lobortis. Curabitur nunc risus, egestas et magna sed, rhoncus sollicitudin urna. Ut vitae vehicula ante.

// Duis consectetur purus eu consequat finibus. Vivamus placerat massa ac massa dictum dictum. Mauris efficitur non arcu vitae convallis. Nullam risus eros, vehicula vel mollis at, mollis eu ante. Donec scelerisque, erat vitae feugiat suscipit, lectus eros mattis dolor, id pretium leo mi id odio. Quisque eget lorem vitae turpis vehicula commodo. Cras a fermentum sapien. Maecenas maximus odio sit amet urna bibendum lacinia. Sed blandit sit amet purus in scelerisque. Quisque lobortis dapibus fermentum. Aliquam id lorem non velit facilisis condimentum. Sed facilisis ac mauris eget pharetra. Curabitur gravida rutrum nisi, nec volutpat lectus. Nullam fringilla nibh ut tincidunt ultricies. Suspendisse potenti.

// Etiam consequat sapien sed felis gravida, vitae interdum diam eleifend. Fusce quis orci ultrices, ornare nulla quis, egestas dolor. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer dignissim, quam in fringilla ultrices, ex leo tempus nisi, in tristique turpis enim sed felis. Quisque sed fermentum tellus. Suspendisse potenti. Integer volutpat erat et sem efficitur lobortis. Nulla enim sem, porttitor ut aliquam id, bibendum sed libero. Ut sit amet varius turpis. Aenean mattis erat sed vehicula blandit. Proin efficitur commodo scelerisque. Mauris risus eros, tristique sollicitudin magna at, auctor fermentum lectus. Praesent cursus cursus ultrices. Suspendisse quis ornare enim. Suspendisse sollicitudin, ligula at porta dignissim, nunc ligula faucibus felis, sit amet dignissim nibh ipsum in risus. Duis consectetur vel elit et finibus.
// Etiam consequat sapien sed felis gravida, vitae interdum diam eleifend. Fusce quis orci ultrices, ornare nulla quis, egestas dolor. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer dignissim, quam in fringilla ultrices, ex leo tempus nisi, in tristique turpis enim sed felis. Quisque sed fermentum tellus. Suspendisse potenti. Integer volutpat erat et sem efficitur lobortis. Nulla enim sem, porttitor ut aliquam id, bibendum sed libero. Ut sit amet varius turpis. Aenean mattis erat sed vehicula blandit. Proin efficitur commodo scelerisque. Mauris risus eros, tristique sollicitudin magna at, auctor fermentum lectus. Praesent cursus cursus ultrices. Suspendisse quis ornare enim. Suspendisse sollicitudin, ligula at porta dignissim, nunc ligula faucibus felis, sit amet dignissim nibh ipsum in risus. Duis consectetur vel elit et finibus.
// Etiam consequat sapien sed felis gravida, vitae interdum diam eleifend. Fusce quis orci ultrices, ornare nulla quis, egestas dolor. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer dignissim, quam in fringilla ultrices, ex leo tempus nisi, in tristique turpis enim sed felis. Quisque sed fermentum tellus. Suspendisse potenti. Integer volutpat erat et sem efficitur lobortis. Nulla enim sem, porttitor ut aliquam id, bibendum sed libero. Ut sit amet varius turpis. Aenean mattis erat sed vehicula blandit. Proin efficitur commodo scelerisque. Mauris risus eros, tristique sollicitudin magna at, auctor fermentum lectus. Praesent cursus cursus ultrices. Suspendisse quis ornare enim. Suspendisse sollicitudin, ligula at porta dignissim, nunc ligula faucibus felis, sit amet dignissim nibh ipsum in risus. Duis consectetur vel elit et finibus.
// Etiam consequat sapien sed felis gravida, vitae interdum diam eleifend. Fusce quis orci ultrices, ornare nulla quis, egestas dolor. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer dignissim, quam in fringilla ultrices, ex leo tempus nisi, in tristique turpis enim sed felis. Quisque sed fermentum tellus. Suspendisse potenti. Integer volutpat erat et sem efficitur lobortis. Nulla enim sem, porttitor ut aliquam id, bibendum sed libero. Ut sit amet varius turpis. Aenean mattis erat sed vehicula blandit. Proin efficitur commodo scelerisque. Mauris risus eros, tristique sollicitudin magna at, auctor fermentum lectus. Praesent cursus cursus ultrices. Suspendisse quis ornare enim. Suspendisse sollicitudin, ligula at porta dignissim, nunc ligula faucibus felis, sit amet dignissim nibh ipsum in risus. Duis consectetur vel elit et finibus.
// Etiam consequat sapien sed felis gravida, vitae interdum diam eleifend. Fusce quis orci ultrices, ornare nulla quis, egestas dolor. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer dignissim, quam in fringilla ultrices, ex leo tempus nisi, in tristique turpis enim sed felis. Quisque sed fermentum tellus. Suspendisse potenti. Integer volutpat erat et sem efficitur lobortis. Nulla enim sem, porttitor ut aliquam id, bibendum sed libero. Ut sit amet varius turpis. Aenean mattis erat sed vehicula blandit. Proin efficitur commodo scelerisque. Mauris risus eros, tristique sollicitudin magna at, auctor fermentum lectus. Praesent cursus cursus ultrices. Suspendisse quis ornare enim. Suspendisse sollicitudin, ligula at porta dignissim, nunc ligula faucibus felis, sit amet dignissim nibh ipsum in risus. Duis consectetur vel elit et finibus.

export default TransactionsList;
