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
import {
	useTransactions,
	useTransactionsSummary,
	useReportingExportLanguage,
} from 'data/index';
import { Transaction } from 'data/transactions/hooks';
import OrderLink from 'components/order-link';
import RiskLevel, { calculateRiskMapping } from 'components/risk-level';
import ClickableCell from 'components/clickable-cell';
import { getDetailsURL } from 'components/details-link';
import { displayType } from 'transactions/strings';
import { displayStatus as displayDepositStatus } from 'deposits/strings';
import {
	formatStringValue,
	isExportModalDismissed,
	getExportLanguage,
	isDefaultSiteLanguage,
	applyThousandSeparator,
} from 'wcpay/utils';
import {
	formatCurrency,
	formatExplicitCurrency,
	formatExportAmount,
} from 'utils/currency';
import { getChargeChannel } from 'utils/charge';
import Deposit from './deposit';
import ConvertedAmount from './converted-amount';
import autocompleter from 'transactions/autocompleter';
import './style.scss';
import TransactionsFilters from '../filters';
import Page from '../../components/page';
import { recordEvent } from 'tracks';
import DownloadButton from 'components/download-button';
import CSVExportModal from 'components/csv-export-modal';
import { getTransactionsCSV } from '../../data/transactions/resolvers';
import p24BankList from '../../payment-details/payment-method/p24/bank-list';
import { HoverTooltip } from 'components/tooltip';
import { PAYMENT_METHOD_TITLES } from 'wcpay/constants/payment-method';
import { ReportingExportLanguageHook } from 'wcpay/settings/reporting-settings/interfaces';

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
			return <Fragment>&nbsp;&nbsp;{ txn.source_identifier }</Fragment>;
		case 'p24':
			return (
				<Fragment>
					&nbsp;&nbsp;
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

const getSourceDeviceIcon = ( txn: Transaction ) => {
	let tooltipDescription = '';

	if ( txn.source_device === 'ios' ) {
		tooltipDescription = __(
			'Tap to Pay on iPhone',
			'woocommerce-payments'
		);
	} else if ( txn.source_device === 'android' ) {
		tooltipDescription = __(
			'Tap to Pay on Android',
			'woocommerce-payments'
		);
	}

	return (
		<HoverTooltip isVisible={ false } content={ tooltipDescription }>
			<span className="woocommerce-taptopay__icon"></span>
		</HoverTooltip>
	);
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
			key: 'customer_currency',
			label: __( 'Paid Currency', 'woocommerce-payments' ),
			screenReaderLabel: __(
				'Customer Currency',
				'woocommerce-payments'
			),
			isSortable: true,
			visible: false,
		},
		{
			key: 'customer_amount',
			label: __( 'Amount Paid', 'woocommerce-payments' ),
			screenReaderLabel: __(
				'Amount in Customer Currency',
				'woocommerce-payments'
			),
			isNumeric: true,
			isSortable: true,
			visible: false,
		},
		{
			key: 'deposit_currency',
			label: __( 'Deposit Currency', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Deposit Currency', 'woocommerce-payments' ),
			isSortable: true,
			visible: false,
		},
		{
			key: 'amount',
			label: __( 'Amount', 'woocommerce-payments' ),
			screenReaderLabel: __(
				'Amount in Deposit Curency',
				'woocommerce-payments'
			),
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
			label: __( 'Payment Method', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Payment Method', 'woocommerce-payments' ),
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
			key: 'deposit_id',
			label: __( 'Deposit ID', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Deposit ID', 'woocommerce-payments' ),
			cellClassName: 'deposit',
			isLeftAligned: true,
			visible: false,
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

	const [ isCSVExportModalOpen, setCSVExportModalOpen ] = useState( false );

	const [
		exportLanguage,
	] = useReportingExportLanguage() as ReportingExportLanguageHook;

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

		const currency = txn.currency.toUpperCase();

		const dataType = txn.metadata ? txn.metadata.charge_type : txn.type;
		const formatAmount = () => {
			const amount = txn.metadata ? 0 : txn.amount;
			const fromAmount = txn.customer_amount ? txn.customer_amount : 0;

			return {
				value: formatExportAmount( amount, currency ),
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
			const feeAmount = formatExportAmount(
				isCardReader ? txn.amount : txn.fees * -1,
				currency
			);
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
		const formatCustomerAmount = () => {
			return {
				value: formatExportAmount(
					txn.customer_amount,
					txn.customer_currency
				),
				display: clickable(
					formatCurrency( txn.customer_amount, txn.customer_currency )
				),
			};
		};

		const isFinancingType =
			-1 !==
			[ 'financing_payout', 'financing_paydown' ].indexOf( txn.type );

		const deposit = ! isFinancingType && (
			<Deposit
				depositId={ txn.deposit_id }
				dateAvailable={ txn.available_on }
			/>
		);

		const depositStatus = txn.deposit_status
			? displayDepositStatus[ txn.deposit_status ]
			: '';

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
				display: clickable(
					<Fragment>
						{ getChargeChannel( txn.channel ) }
						{ txn.source_device && getSourceDeviceIcon( txn ) }
					</Fragment>
				),
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
							<HoverTooltip
								isVisible={ false }
								content={ PAYMENT_METHOD_TITLES[ txn.source ] }
							>
								<span
									className={ `payment-method__brand payment-method__brand--${ txn.source }` }
									aria-label={
										PAYMENT_METHOD_TITLES[ txn.source ]
									}
								/>
							</HoverTooltip>
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
			customer_currency: {
				value: txn.customer_currency.toUpperCase(),
				display: clickable( txn.customer_currency.toUpperCase() ),
			},
			customer_amount: formatCustomerAmount(),
			deposit_currency: {
				value: txn.currency.toUpperCase(),
				display: clickable( txn.currency.toUpperCase() ),
			},
			amount: formatAmount(),
			// fees should display as negative. The format $-9.99 is determined by WC-Admin
			fees: formatFees(),
			net: {
				value: formatExportAmount( txn.net, currency ),
				display: clickable(
					formatExplicitCurrency( txn.net, currency )
				),
			},
			risk_level: {
				value: calculateRiskMapping( txn.risk_level ),
				display: clickable( riskLevel ),
			},
			deposit_id: {
				value: txn.deposit_id,
				display: txn.deposit_id,
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

	const endpointExport = async ( language: string ) => {
		// We destructure page and path to get the right params.
		// eslint-disable-next-line @typescript-eslint/no-unused-vars
		const { page, path, ...params } = getQuery();
		const userEmail = wcpaySettings.currentUserEmail;

		const locale = getExportLanguage( language, exportLanguage );
		const {
			date_after: dateAfter,
			date_before: dateBefore,
			date_between: dateBetween,
			match,
			search,
			type_is: typeIs,
			type_is_not: typeIsNot,
			source_device_is: sourceDeviceIs,
			source_device_is_not: sourceDeviceIsNot,
			channel_is: channelIs,
			channel_is_not: channelIsNot,
			customer_country_is: customerCountryIs,
			customer_country_is_not: customerCountryIsNot,
			risk_level_is: riskLevelIs,
			risk_level_is_not: riskLevelIsNot,
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
			!! typeIsNot ||
			!! channelIs ||
			!! channelIsNot ||
			!! customerCountryIs ||
			!! customerCountryIsNot ||
			!! riskLevelIs ||
			!! riskLevelIsNot ||
			!! sourceDeviceIs ||
			!! sourceDeviceIsNot;

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
				await apiFetch( {
					path: getTransactionsCSV( {
						userEmail,
						locale,
						dateAfter,
						dateBefore,
						dateBetween,
						match,
						search,
						typeIs,
						typeIsNot,
						sourceDeviceIs,
						sourceDeviceIsNot,
						customerCurrencyIs,
						customerCurrencyIsNot,
						channelIs,
						channelIsNot,
						customerCountryIs,
						customerCountryIsNot,
						riskLevelIs,
						riskLevelIsNot,
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
	};

	const onDownload = async () => {
		setIsDownloading( true );

		// We destructure page and path to get the right params.
		// eslint-disable-next-line @typescript-eslint/no-unused-vars
		const { page, path, ...params } = getQuery();
		const downloadType = totalRows > rows.length ? 'endpoint' : 'browser';

		recordEvent( 'wcpay_transactions_download_csv_click', {
			location: props.depositId ? 'deposit_details' : 'transactions',
			download_type: downloadType,
			exported_transactions: rows.length,
			total_transactions: transactionsSummary.count,
		} );

		if ( 'endpoint' === downloadType ) {
			if ( ! isDefaultSiteLanguage() && ! isExportModalDismissed() ) {
				setCSVExportModalOpen( true );
			} else {
				endpointExport( '' );
			}
		} else {
			downloadCSVFile(
				generateCSVFileName( title, params ),
				generateCSVDataFromTable( columnsToDisplay, rows )
			);
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

	const closeModal = () => {
		setCSVExportModalOpen( false );
	};

	const exportTransactions = ( language: string ) => {
		endpointExport( language );

		closeModal();
	};

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

			{ ! isDefaultSiteLanguage() &&
				! isExportModalDismissed() &&
				isCSVExportModalOpen && (
					<CSVExportModal
						onClose={ closeModal }
						onSubmit={ exportTransactions }
						totalItems={ totalRows }
						exportType={ 'transactions' }
					/>
				) }
		</Page>
	);
};

export default TransactionsList;
