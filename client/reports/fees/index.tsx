/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Link,
	Search,
	TableCard,
	TableCardBodyColumn,
} from '@woocommerce/components';
import {
	getQuery,
	onQueryChange,
	updateQueryString,
} from '@woocommerce/navigation';
import { uniq } from 'lodash';

/**
 * Internal dependencies
 */
import { useReportsFees, useReportsFeesSummary } from 'wcpay/data';
import type { ReportsFee } from 'wcpay/data/reports/hooks';
import ClickableCell from 'wcpay/components/clickable-cell';
import { getDetailsURL } from 'wcpay/components/details-link';
import { formatDateTimeFromString } from 'wcpay/utils/date-time';
import {
	applyThousandSeparator,
	formatStringValue,
	getAdminUrl,
} from 'wcpay/utils';
import { formatExplicitCurrency } from 'multi-currency/interface/functions';
import { usePersistedColumnVisibility } from 'wcpay/hooks/use-persisted-table-column-visibility';
import { useReportExport } from 'wcpay/hooks/use-report-export';
import DownloadButton from 'wcpay/components/download-button';
import { recordEvent } from 'tracks';
import {
	getReportsFeesCSVRequestURL,
	reportsFeesDownloadEndpoint,
} from 'wcpay/data/reports/resolvers';
import { FeesFilters } from './filters';
import {
	Column,
	FeesColumnKey,
	emptyFeesValue,
	getFeesColumnCell,
	getFeesColumns,
} from './columns';
import { displayMethod, displayType } from './strings';
import type { ReportsPeriodRange } from '../period-selector';

interface FeesReportProps {
	period: ReportsPeriodRange;
	onReload?: () => void;
}

interface CompletionOption {
	key: string;
	label: string;
}

type FeesReportQuery = ReturnType< typeof getQuery > & {
	payment_method_type?: string;
	type?: string | string[];
	order_id?: string;
	deposit_id?: string;
	customer_email?: string;
};

const feesFilterKeys: Array< keyof FeesReportQuery > = [
	'date_before',
	'date_after',
	'date_between',
	'payment_method_type',
	'type',
	'order_id',
	'deposit_id',
	'customer_email',
	'search',
	'match',
];

const getPeriodDateBetween = ( period: ReportsPeriodRange ): string[] => [
	period.start.slice( 0, 10 ),
	period.end.slice( 0, 10 ),
];

export const getFeesQuery = (
	query: FeesReportQuery,
	period: ReportsPeriodRange
): FeesReportQuery => {
	if (
		query.filter === 'advanced' ||
		query.filter === 'all' ||
		query.date_before ||
		query.date_after ||
		query.date_between
	) {
		return query;
	}

	return {
		...query,
		date_between: getPeriodDateBetween( period ),
	};
};

export const hasActiveFeesFilters = ( query: FeesReportQuery ): boolean =>
	feesFilterKeys.some( ( key ) => {
		const value = query[ key ];
		return Array.isArray( value ) ? value.length > 0 : Boolean( value );
	} );

const getFeesFiltersQuery = ( feesQuery: FeesReportQuery ): FeesReportQuery => {
	if ( ! hasActiveFeesFilters( feesQuery ) ) {
		return feesQuery;
	}

	return {
		...feesQuery,
		filter: 'advanced',
	};
};

const getOrderUrl = ( orderId: ReportsFee[ 'order_id' ] ): string =>
	getAdminUrl( {
		page: 'wc-orders',
		action: 'edit',
		id: orderId ?? '',
	} );

const getFeesSearchOptions = ( feesRows: ReportsFee[] ): CompletionOption[] =>
	uniq(
		feesRows.flatMap( ( row ) =>
			[
				row.transaction_id,
				row.order_id ? String( row.order_id ) : '',
				row.deposit_id || '',
			].filter( Boolean )
		)
	).map( ( value ) => ( {
		key: value,
		label: value,
	} ) );

export const getFeesSearchAutocompleter = ( feesRows: ReportsFee[] ) => ( {
	name: 'fees',
	className: 'woocommerce-search__fees-result',
	options( term: string ): Promise< CompletionOption[] > {
		const options = getFeesSearchOptions( feesRows );
		const normalizedTerm = term.toLowerCase();

		return Promise.resolve(
			normalizedTerm
				? options.filter( ( option ) =>
						option.label.toLowerCase().includes( normalizedTerm )
				  )
				: options
		);
	},
	isDebounced: true,
	getOptionIdentifier( option: CompletionOption ): string {
		return option.label;
	},
	getOptionKeywords( option: CompletionOption ): string[] {
		return [ option.label ];
	},
	getOptionLabel( option: CompletionOption ): JSX.Element {
		return (
			<span
				key={ option.key }
				className="woocommerce-search__result-name"
				aria-label={ option.label }
			>
				{ option.label }
			</span>
		);
	},
	getOptionCompletion( option: CompletionOption ): CompletionOption {
		return {
			key: option.label,
			label: option.label,
		};
	},
} );

const getTransactionURL = ( row: ReportsFee ): string => {
	const detailsURL = getDetailsURL(
		row.payment_id || row.transaction_id,
		'transactions'
	);

	return `${ detailsURL }&transaction_id=${ row.transaction_id }&transaction_type=${ row.type }`;
};

const getFeesRowContent = (
	row: ReportsFee
): Record< FeesColumnKey, TableCardBodyColumn > => {
	const transactionURL = getTransactionURL( row );
	const clickable = ( children: React.ReactNode ) => (
		<ClickableCell href={ transactionURL }>{ children }</ClickableCell>
	);
	const paymentMethodType = row.payment_method?.type || '';
	const rowTypeLabel =
		displayType[ row.type as keyof typeof displayType ] ||
		formatStringValue( row.type );
	const depositCurrency = row.deposit_currency;
	const formattedDate = formatDateTimeFromString( row.date, {
		includeTime: true,
	} );
	const formattedDepositDate = row.deposit_date
		? formatDateTimeFromString( row.deposit_date )
		: emptyFeesValue;

	return {
		date: {
			value: row.date,
			display: clickable( formattedDate ),
		},
		payment_method: {
			value: paymentMethodType,
			display: displayMethod( paymentMethodType ),
		},
		type: {
			value: rowTypeLabel,
			display: clickable( rowTypeLabel ),
		},
		order_id: {
			value: row.order_id || '',
			display: row.order_id ? (
				<Link href={ getOrderUrl( row.order_id ) }>
					{ row.order_id }
				</Link>
			) : (
				emptyFeesValue
			),
		},
		transaction_id: {
			value: row.transaction_id,
			display: clickable( row.transaction_id ),
		},
		transaction_currency: {
			value: row.transaction_currency.toUpperCase(),
			display: clickable( row.transaction_currency.toUpperCase() ),
		},
		amount: {
			value: row.amount,
			display: clickable(
				formatExplicitCurrency( row.amount, depositCurrency )
			),
		},
		fees: {
			value: row.fees,
			display: clickable(
				formatExplicitCurrency( row.fees, depositCurrency )
			),
		},
		deposit_date: {
			value: row.deposit_date || '',
			display: row.deposit_date
				? clickable( formattedDepositDate )
				: emptyFeesValue,
		},
		deposit_id: getFeesColumnCell( row, 'deposit_id' ),
	};
};

const getFeesRows = (
	feesRows: ReportsFee[],
	columnsToDisplay: Column[]
): TableCardBodyColumn[][] =>
	feesRows.map( ( row ) => {
		const data = getFeesRowContent( row );

		return columnsToDisplay.map(
			( { key } ) => data[ key ] || { display: null }
		);
	} );

export const FeesReport = ( {
	period,
	onReload = () => undefined,
}: FeesReportProps ): JSX.Element => {
	const query = getQuery() as FeesReportQuery;
	const feesQuery = getFeesQuery( query, period );
	const { feesRows, feesError = {}, isLoading } = useReportsFees( feesQuery );
	const { feesSummary, isLoading: isSummaryLoading } =
		useReportsFeesSummary( feesQuery );
	const { onColumnsChange, columnsToDisplay } =
		usePersistedColumnVisibility< Column >(
			'wc_payments_reports_fees_hidden_columns',
			getFeesColumns()
		);
	const onFeesColumnsChange = ( shownColumns: string[], key?: string ) => {
		void key;
		recordEvent( 'wcpay_reports_view_options_opened', {
			report: 'fees',
		} );
		onColumnsChange( shownColumns );
	};
	const { requestReportExport, isExportInProgress } = useReportExport();
	const { createNotice } = useDispatch( 'core/notices' );

	const totalRows = feesSummary.count || 0;
	const hasError = Object.keys( feesError ).length > 0;
	const isEmptyPeriod =
		! isLoading &&
		! hasError &&
		feesRows.length === 0 &&
		! hasActiveFeesFilters( query );

	if ( hasError ) {
		return (
			<div
				className="wcpay-reports-state wcpay-reports-state--error"
				role="group"
				aria-labelledby="wcpay-reports-fees-error"
			>
				<h2 id="wcpay-reports-fees-error">
					{ __( 'Fees report unavailable', 'woocommerce-payments' ) }
				</h2>
				<Button variant="secondary" onClick={ onReload }>
					{ __( 'Reload report', 'woocommerce-payments' ) }
				</Button>
			</div>
		);
	}

	if ( isEmptyPeriod ) {
		return (
			<div className="wcpay-reports-state wcpay-reports-state--empty">
				<h2>{ __( 'No fees yet', 'woocommerce-payments' ) }</h2>
				<p>
					{ __(
						'Fees will appear here once you start receiving payments.',
						'woocommerce-payments'
					) }
				</p>
			</div>
		);
	}

	const rows = getFeesRows( feesRows, columnsToDisplay );
	const searchedLabels =
		query.search &&
		query.search.map( ( value ) => ( {
			key: value,
			label: value,
		} ) );
	const onSearchChange = ( values: CompletionOption[] ) => {
		updateQueryString(
			{
				search: values.length
					? uniq( values.map( ( value ) => value.label ) )
					: undefined,
			},
			'/payments/reports'
		);
	};
	const onExport = () => {
		recordEvent( 'wcpay_reports_export_click', {
			report: 'fees',
			exported_row_count: totalRows,
		} );

		const userEmail = wcpaySettings.currentUserEmail;
		const locale = wcSettings.locale.userLocale;
		const exportRequestURL = getReportsFeesCSVRequestURL( {
			match: feesQuery.match,
			dateBefore: feesQuery.date_before,
			dateAfter: feesQuery.date_after,
			dateBetween: feesQuery.date_between,
			paymentMethodType: feesQuery.payment_method_type,
			type: feesQuery.type,
			orderId: feesQuery.order_id,
			depositId: feesQuery.deposit_id,
			customerEmail: feesQuery.customer_email,
			search: feesQuery.search,
			orderby: feesQuery.orderby || 'date',
			order: feesQuery.order || 'desc',
			userEmail,
			locale,
		} );
		const confirmThreshold = 10000;
		const confirmMessage = sprintf(
			__(
				"You are about to export %d fees. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?",
				'woocommerce-payments'
			),
			totalRows
		);

		if (
			hasActiveFeesFilters( query ) ||
			totalRows < confirmThreshold ||
			window.confirm( confirmMessage )
		) {
			requestReportExport( {
				exportRequestURL,
				exportFileAvailabilityEndpoint: reportsFeesDownloadEndpoint,
				userEmail,
			} );

			createNotice(
				'success',
				sprintf(
					__(
						"We're processing your export. The file will download automatically and be emailed to %s.",
						'woocommerce-payments'
					),
					userEmail
				)
			);
		}
	};

	const summary =
		feesSummary.count !== undefined && ! isSummaryLoading
			? [
					{
						label: _n(
							'fee',
							'fees',
							feesSummary.count,
							'woocommerce-payments'
						),
						value: `${ applyThousandSeparator(
							feesSummary.count
						) }`,
					},
					{
						label: __( 'gross total', 'woocommerce-payments' ),
						value: `${ formatExplicitCurrency(
							feesSummary.total ?? 0,
							feesSummary.currency || ''
						) }`,
					},
					{
						label: __( 'fees total', 'woocommerce-payments' ),
						value: `${ formatExplicitCurrency(
							feesSummary.fees ?? 0,
							feesSummary.currency || ''
						) }`,
					},
			  ]
			: undefined;
	const downloadable = rows.length > 0;

	return (
		<>
			<FeesFilters
				feesSummary={ feesSummary }
				query={ getFeesFiltersQuery( feesQuery ) }
			/>
			<TableCard
				className="fees-report woocommerce-report-table has-search"
				title={ __( 'Fees', 'woocommerce-payments' ) }
				isLoading={ isLoading }
				rowsPerPage={ parseInt( query.per_page ?? '', 10 ) || 25 }
				totalRows={ totalRows }
				headers={ columnsToDisplay }
				rows={ rows }
				summary={ summary }
				query={ query }
				onQueryChange={ onQueryChange }
				onColumnsChange={ onFeesColumnsChange }
				actions={ [
					<Search
						allowFreeTextSearch={ false }
						inlineTags
						key="search"
						onChange={ onSearchChange }
						placeholder={ __(
							'Search by transaction ID, order ID, or payout ID',
							'woocommerce-payments'
						) }
						selected={ searchedLabels }
						showClearButton={ true }
						type="custom"
						autocompleter={ getFeesSearchAutocompleter( feesRows ) }
					/>,
					downloadable && (
						<DownloadButton
							key="download"
							isDisabled={ isLoading || isExportInProgress }
							isBusy={ isExportInProgress }
							onClick={ onExport }
						/>
					),
				] }
			/>
			<p className="wcpay-reports-fees__date-basis-note">
				{ __(
					'Dates reflect when each event was created - settlement-date reporting is coming.',
					'woocommerce-payments'
				) }
			</p>
		</>
	);
};

export default FeesReport;
