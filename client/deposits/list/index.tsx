/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { recordEvent } from 'tracks';
import { __, _n, sprintf } from '@wordpress/i18n';
import { TableCard, Link } from '@woocommerce/components';
import { onQueryChange, getQuery } from '@woocommerce/navigation';
import { useDispatch } from '@wordpress/data';
import { parseInt } from 'lodash';

/**
 * Internal dependencies.
 */
import type { DepositsTableHeader } from 'wcpay/types/deposits';
import { useDeposits, useDepositsSummary } from 'wcpay/data';
import { displayType, depositStatusLabels } from '../strings';
import {
	formatExplicitCurrency,
	formatExportAmount,
} from 'multi-currency/interface/functions';
import DetailsLink, { getDetailsURL } from 'components/details-link';
import ClickableCell from 'components/clickable-cell';
import Page from '../../components/page';
import DepositsFilters from '../filters';
import DownloadButton from 'components/download-button';
import {
	getPayoutsCSVRequestURL,
	payoutsDownloadEndpoint,
} from 'wcpay/data/deposits/resolvers';
import { applyThousandSeparator } from '../../utils/index.js';
import DepositStatusChip from 'components/deposit-status-chip';
import { useReportExport } from 'wcpay/hooks/use-report-export';

import './style.scss';
import { formatDateTimeFromString } from 'wcpay/utils/date-time';
import { usePersistedColumnVisibility } from 'wcpay/hooks/use-persisted-table-column-visibility';

const getColumns = ( sortByDate?: boolean ): DepositsTableHeader[] => [
	{
		key: 'details',
		label: '',
		required: true,
		cellClassName: 'info-button ' + ( sortByDate ? 'is-sorted' : '' ),
		isLeftAligned: true,
	},
	{
		key: 'date',
		label: __( 'Date', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Date', 'woocommerce-payments' ),
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
		required: true,
		isSortable: true,
	},
	{
		key: 'status',
		label: __( 'Status', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Status', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
	},
	// TODO { key: 'transactions', label: __( 'Transactions', 'woocommerce-payments' ), isNumeric: true },
	{
		key: 'bankAccount',
		label: __( 'Bank account', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Bank account', 'woocommerce-payments' ),
		isLeftAligned: true,
	},
	{
		key: 'bankReferenceId',
		label: __( 'Bank reference ID', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Bank reference ID', 'woocommerce-payments' ),
	},
];

export const DepositsList = (): JSX.Element => {
	const { deposits, isLoading } = useDeposits( getQuery() );
	const { depositsSummary, isLoading: isSummaryLoading } = useDepositsSummary(
		getQuery()
	);

	const { requestReportExport, isExportInProgress } = useReportExport();
	const { createNotice } = useDispatch( 'core/notices' );

	const sortByDate = ! getQuery().orderby || 'date' === getQuery().orderby;
	const columns = getColumns( sortByDate );
	const { columnsToDisplay, onColumnsChange } = usePersistedColumnVisibility<
		DepositsTableHeader
	>( 'wc_payments_payouts_hidden_columns', columns );

	const totalRows = depositsSummary.count || 0;

	const rows = deposits.map( ( deposit ) => {
		const clickable = ( children: React.ReactNode ): JSX.Element => (
			<ClickableCell
				href={ getDetailsURL( deposit.id, 'payouts' ) }
				onClick={ () => recordEvent( 'wcpay_deposits_row_click' ) }
			>
				{ children }
			</ClickableCell>
		);
		const detailsLink = (
			<DetailsLink id={ deposit.id } parentSegment="payouts" />
		);

		const dateDisplay = (
			<Link
				href={ getDetailsURL( deposit.id, 'payouts' ) }
				onClick={ () => recordEvent( 'wcpay_deposits_row_click' ) }
			>
				{ formatDateTimeFromString( deposit.date ) }
			</Link>
		);

		// Map deposit to table row.
		const data = {
			details: { value: deposit.id, display: detailsLink },
			date: { value: deposit.date, display: dateDisplay },
			type: {
				value: displayType[ deposit.type ],
				display: clickable( displayType[ deposit.type ] ),
			},
			amount: {
				value: formatExportAmount( deposit.amount, deposit.currency ),
				display: clickable(
					formatExplicitCurrency( deposit.amount, deposit.currency )
				),
			},
			status: {
				value: depositStatusLabels[ deposit.status ],
				display: clickable( <DepositStatusChip deposit={ deposit } /> ),
			},
			bankAccount: {
				value: deposit.bankAccount,
				display: clickable( deposit.bankAccount ),
			},
			bankReferenceId: {
				value: deposit.bank_reference_key,
				display: clickable( deposit.bank_reference_key ?? 'N/A' ),
			},
		};

		return columnsToDisplay.map(
			( { key } ) => data[ key ] || { display: null }
		);
	} );

	const isCurrencyFiltered = 'string' === typeof getQuery().store_currency_is;
	const isSingleCurrency =
		2 > ( depositsSummary.store_currencies || [] ).length;

	// initializing summary with undefined as we don't want to render the TableSummary component unless we have the data
	let summary;
	const isDespositsSummaryDataLoaded =
		depositsSummary.count !== undefined &&
		depositsSummary.total !== undefined &&
		false === isSummaryLoading;

	// Generate summary only if the data has been loaded
	if ( isDespositsSummaryDataLoaded ) {
		summary = [
			{
				label: _n(
					'payout',
					'payouts',
					depositsSummary.count,
					'woocommerce-payments'
				),
				value: `${ applyThousandSeparator(
					depositsSummary.count as number
				) }`,
			},
		];

		if ( isSingleCurrency || isCurrencyFiltered ) {
			summary.push( {
				label: __( 'total', 'woocommerce-payments' ),
				value: `${ formatExplicitCurrency(
					depositsSummary.total,
					depositsSummary.currency
				) }`,
			} );
		}
	}

	const storeCurrencies =
		depositsSummary.store_currencies ||
		( isCurrencyFiltered ? [ getQuery().store_currency_is ] : [] );

	const downloadable = !! rows.length;

	const { path } = getQuery();
	const onExport = async () => {
		recordEvent( 'wcpay_csv_export_click', {
			row_type: 'payouts',
			source: path,
			exported_row_count: depositsSummary.count,
		} );

		const userEmail = wcpaySettings.currentUserEmail;
		const locale = wcSettings.locale.userLocale;

		const {
			date_before: dateBefore,
			date_after: dateAfter,
			date_between: dateBetween,
			match,
			status_is: statusIs,
			status_is_not: statusIsNot,
			store_currency_is: storeCurrencyIs,
		} = getQuery();

		const exportRequestURL = getPayoutsCSVRequestURL( {
			userEmail,
			locale,
			dateBefore,
			dateAfter,
			dateBetween,
			match,
			statusIs,
			statusIsNot,
			storeCurrencyIs,
		} );

		const isFiltered =
			!! dateBefore ||
			!! dateAfter ||
			!! dateBetween ||
			!! statusIs ||
			!! statusIsNot ||
			!! storeCurrencyIs;

		const confirmThreshold = 1000;
		const confirmMessage = sprintf(
			__(
				"You are about to export %d deposits. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?",
				'woocommerce-payments'
			),
			totalRows
		);

		if (
			isFiltered ||
			totalRows < confirmThreshold ||
			window.confirm( confirmMessage )
		) {
			requestReportExport( {
				exportRequestURL,
				exportFileAvailabilityEndpoint: payoutsDownloadEndpoint,
				userEmail,
			} );

			createNotice(
				'success',
				sprintf(
					__(
						'We’re processing your export. 🎉 The file will download automatically and be emailed to %s.',
						'woocommerce-payments'
					),
					userEmail
				)
			);
		}
	};

	return (
		<Page>
			<DepositsFilters storeCurrencies={ storeCurrencies } />
			<TableCard
				className="wcpay-deposits-list woocommerce-report-table"
				title={ __( 'Payout history', 'woocommerce-payments' ) }
				isLoading={ isLoading }
				rowsPerPage={ parseInt( getQuery().per_page ?? '' ) || 25 }
				totalRows={ totalRows }
				headers={ columnsToDisplay }
				rows={ rows }
				summary={ summary }
				query={ getQuery() }
				onQueryChange={ onQueryChange }
				onColumnsChange={ onColumnsChange }
				actions={ [
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
		</Page>
	);
};

export default DepositsList;
