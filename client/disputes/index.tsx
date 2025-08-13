/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { recordEvent } from 'tracks';
import { _n, __, sprintf } from '@wordpress/i18n';
import moment from 'moment';
import { Button } from '@wordpress/components';
import { TableCard, Link } from '@woocommerce/components';
import { onQueryChange, getQuery, getHistory } from '@woocommerce/navigation';
import clsx from 'clsx';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';

/**
 * Internal dependencies.
 */
import { useDisputes, useDisputesSummary } from 'data/index';
import OrderLink from 'components/order-link';
import DisputeStatusChip from 'components/dispute-status-chip';
import ClickableCell from 'components/clickable-cell';
import DetailsLink, { getDetailsURL } from 'components/details-link';
import Page from 'components/page';
import { TestModeNotice } from 'components/test-mode-notice';
import { reasons } from './strings';
import { formatStringValue } from 'utils';
import {
	formatExplicitCurrency,
	formatExportAmount,
} from 'multi-currency/interface/functions';
import DisputesFilters from './filters';
import DownloadButton from 'components/download-button';
import { CachedDispute, DisputesTableHeader } from 'wcpay/types/disputes';
import {
	getDisputesCSVRequestURL,
	disputesDownloadEndpoint,
} from 'wcpay/data/disputes/resolvers';
import { applyThousandSeparator } from 'wcpay/utils';
import { useSettings } from 'wcpay/data';
import { isAwaitingResponse } from 'wcpay/disputes/utils';
import './style.scss';
import { formatDateTimeFromString } from 'wcpay/utils/date-time';
import { usePersistedColumnVisibility } from 'wcpay/hooks/use-persisted-table-column-visibility';
import { useReportExport } from 'wcpay/hooks/use-report-export';
import { useDispatch } from '@wordpress/data';
import { MaybeShowMerchantFeedbackPrompt } from 'wcpay/merchant-feedback-prompt';

const getHeaders = ( sortColumn?: string ): DisputesTableHeader[] => [
	{
		key: 'details',
		label: '',
		required: true,
		cellClassName: clsx( 'info-button', {
			'is-sorted': sortColumn === 'amount',
		} ),
		isLeftAligned: true,
	},
	{
		key: 'amount',
		label: __( 'Amount', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Amount', 'woocommerce-payments' ),
		required: true,
		isSortable: true,
		isLeftAligned: true,
	},
	{
		key: 'currency',
		label: __( 'Currency', 'woocommerce-payments' ),
		visible: false,
		required: true,
	},
	{
		key: 'status',
		label: __( 'Status', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Status', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
	},
	{
		key: 'reason',
		label: __( 'Reason', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Reason', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
	},
	{
		key: 'source',
		label: __( 'Source', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Source', 'woocommerce-payments' ),
		required: true,
		cellClassName: 'is-center-aligned',
	},
	{
		key: 'order',
		label: __( 'Order #', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Order #', 'woocommerce-payments' ),
		required: true,
	},
	{
		key: 'customerName',
		label: __( 'Customer', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Customer', 'woocommerce-payments' ),
		isLeftAligned: true,
	},
	{
		key: 'customerEmail',
		label: __( 'Email', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Email', 'woocommerce-payments' ),
		visible: false,
		isLeftAligned: true,
	},
	{
		key: 'customerCountry',
		label: __( 'Country', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Country', 'woocommerce-payments' ),
		visible: false,
		isLeftAligned: true,
	},
	{
		key: 'created',
		label: __( 'Disputed on', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Disputed on', 'woocommerce-payments' ),
		isLeftAligned: true,
		isSortable: true,
		defaultSort: true,
		defaultOrder: 'desc',
		visible: false,
	},
	{
		key: 'dueBy',
		label: __( 'Respond by', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Respond by', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
		isSortable: true,
	},
	{
		key: 'action',
		label: __( 'Action', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Action', 'woocommerce-payments' ),
		isLeftAligned: false,
		isNumeric: true,
		required: true,
		visible: true,
	},
];

/**
 * Returns a smart date if dispute's due date is within 72 hours.
 * Otherwise, returns a date string.
 *
 * @param {CachedDispute} dispute The dispute to check.
 *
 * @return {JSX.Element | string} If dispute is due within 72 hours, return the element that display smart date. Otherwise, a date string.
 */
const smartDueDate = ( dispute: CachedDispute ) => {
	// if dispute is not awaiting response, return an empty string.
	if ( dispute.due_by === '' || ! isAwaitingResponse( dispute.status ) ) {
		return '';
	}
	// Get current time in UTC.
	const now = moment().utc();
	const dueBy = moment.utc( dispute.due_by );
	const diffHours = dueBy.diff( now, 'hours', false );
	const diffDays = dueBy.diff( now, 'days', false );

	// if the dispute is past due, return an empty string.
	if ( diffHours <= 0 ) {
		return '';
	}
	if ( diffHours <= 72 ) {
		return (
			<span className="due-soon">
				{ diffHours <= 24
					? __( 'Last day today', 'woocommerce-payments' )
					: sprintf(
							// Translators: %s is the number of days left to respond to the dispute.
							_n(
								'%s day left',
								'%s days left',
								diffDays,
								'woocommerce-payments'
							),
							diffDays
					  ) }
				<NoticeOutlineIcon className="due-soon-icon" />
			</span>
		);
	}
	return formatDateTimeFromString( dispute.due_by, {
		includeTime: true,
	} );
};

export const DisputesList = (): JSX.Element => {
	// pre-fetching the settings.
	useSettings();

	const { disputes, isLoading } = useDisputes( getQuery() );

	const { disputesSummary, isLoading: isSummaryLoading } = useDisputesSummary(
		getQuery()
	);

	const { requestReportExport, isExportInProgress } = useReportExport();

	const { createNotice } = useDispatch( 'core/notices' );

	const headers = getHeaders( getQuery().orderby );
	const { columnsToDisplay, onColumnsChange } = usePersistedColumnVisibility<
		DisputesTableHeader
	>( 'wc_payments_disputes_hidden_columns', headers );

	const totalRows = disputesSummary.count || 0;

	const rows = disputes.map( ( dispute ) => {
		const onClickDisputeRow = (
			e: React.MouseEvent< HTMLAnchorElement >
		) => {
			// Use client-side routing to avoid page refresh.
			e.preventDefault();
			recordEvent( 'wcpay_disputes_row_action_click' );
			const history = getHistory();
			history.push( getDetailsURL( dispute.charge_id, 'transactions' ) );
		};
		const clickable = ( children: React.ReactNode ): JSX.Element => (
			<ClickableCell
				href={ getDetailsURL( dispute.charge_id, 'transactions' ) }
				onClick={ onClickDisputeRow }
			>
				{ children }
			</ClickableCell>
		);

		const detailsLink = (
			<DetailsLink
				id={ dispute.charge_id }
				parentSegment="transactions"
			/>
		);

		const reasonMapping = reasons[ dispute.reason ];
		const reasonDisplay = reasonMapping
			? reasonMapping.display
			: formatStringValue( dispute.reason );
		const needsResponse = isAwaitingResponse( dispute.status );
		const data: {
			[ key: string ]: {
				value: number | string;
				display: JSX.Element;
			};
		} = {
			amount: {
				value: formatExportAmount( dispute.amount, dispute.currency ),
				display: clickable(
					formatExplicitCurrency( dispute.amount, dispute.currency )
				),
			},
			currency: {
				value: dispute.currency,
				display: clickable( dispute.currency ),
			},
			status: {
				value: dispute.status,
				display: clickable(
					<DisputeStatusChip status={ dispute.status } />
				),
			},
			reason: {
				value: reasonDisplay,
				display: clickable( reasonDisplay ),
			},
			source: {
				value: dispute.source ?? '',
				display: clickable(
					<span
						className={ `payment-method__brand payment-method__brand--${
							dispute.source ?? ''
						}` }
					/>
				),
			},
			created: {
				value: dispute.created,
				display: clickable(
					formatDateTimeFromString( dispute.created, {
						includeTime: true,
					} )
				),
			},
			dueBy: {
				value: dispute.due_by,
				display: clickable( smartDueDate( dispute ) ),
			},
			order: {
				value: dispute.order_number ?? '',
				display: <OrderLink order={ dispute.order } />,
			},
			customerName: {
				value: dispute.customer_name ?? '',
				display:
					dispute.order && dispute.order.customer_url ? (
						<Link href={ dispute.order.customer_url }>
							{ dispute.customer_name }
						</Link>
					) : (
						clickable( dispute.customer_name )
					),
			},
			customerEmail: {
				value: dispute.customer_email ?? '',
				display: clickable( dispute.customer_email ),
			},
			customerCountry: {
				value: dispute.customer_country ?? '',
				display: clickable( dispute.customer_country ),
			},
			details: { value: dispute.dispute_id, display: detailsLink },
			action: {
				value: '',
				display: (
					<Button
						variant={ needsResponse ? 'secondary' : 'tertiary' }
						href={ getDetailsURL(
							dispute.charge_id,
							'transactions'
						) }
						onClick={ onClickDisputeRow }
					>
						{ needsResponse
							? __( 'Respond', 'woocommerce-payments' )
							: __( 'See details', 'woocommerce-payments' ) }
					</Button>
				),
			},
		};
		return headers.map(
			( { key } ) => data[ key ] || { value: undefined, display: null }
		);
	} );

	const downloadable = !! rows.length;

	const onDownload = async () => {
		// We destructure page and path to get the right params.
		// eslint-disable-next-line @typescript-eslint/no-unused-vars
		const { page, path, ...params } = getQuery();
		const userEmail = wcpaySettings.currentUserEmail;

		const locale = wcSettings.locale.userLocale;
		recordEvent( 'wcpay_csv_export_click', {
			row_type: 'disputes',
			source: path,
			exported_row_count: disputesSummary.count,
		} );

		const {
			date_before: dateBefore,
			date_after: dateAfter,
			date_between: dateBetween,
			match,
			filter,
			status_is: statusIs,
			status_is_not: statusIsNot,
		} = getQuery();

		const exportRequestURL = getDisputesCSVRequestURL( {
			userEmail,
			locale,
			dateAfter,
			dateBefore,
			dateBetween,
			match,
			filter,
			statusIs,
			statusIsNot,
		} );

		const isFiltered =
			!! dateBefore ||
			!! dateAfter ||
			!! dateBetween ||
			!! statusIs ||
			!! statusIsNot;

		const confirmThreshold = 1000;
		const confirmMessage = sprintf(
			__(
				"You are about to export %d disputes. If you'd like to reduce the size of your export, you can use one or more filters. Would you like to continue?",
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
				exportFileAvailabilityEndpoint: disputesDownloadEndpoint,
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

	let summary;
	const isDisputesSummaryDataLoaded =
		disputesSummary.count !== undefined && ! isSummaryLoading;
	if ( isDisputesSummaryDataLoaded ) {
		summary = [
			{
				label: _n(
					'dispute',
					'disputes',
					disputesSummary.count ?? 0,
					'woocommerce-payments'
				),
				value: `${ applyThousandSeparator(
					disputesSummary.count as number
				) }`,
			},
		];
	}

	const isCurrencyFiltered = 'string' === typeof getQuery().store_currency_is;

	const storeCurrencies =
		disputesSummary.currencies ||
		( isCurrencyFiltered ? [ getQuery().store_currency_is ?? '' ] : [] );

	return (
		<Page>
			<MaybeShowMerchantFeedbackPrompt />
			<TestModeNotice currentPage="disputes" />
			<DisputesFilters storeCurrencies={ storeCurrencies } />
			<TableCard
				className="wcpay-disputes-list"
				title={ __( 'Disputes', 'woocommerce-payments' ) }
				isLoading={ isLoading }
				rowsPerPage={ parseInt( getQuery().per_page ?? '', 10 ) || 25 }
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
							onClick={ onDownload }
						/>
					),
				] }
			/>
		</Page>
	);
};

export default DisputesList;
