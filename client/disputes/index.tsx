/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
import wcpayTracks from 'tracks';
import { dateI18n } from '@wordpress/date';
import { _n, __, sprintf } from '@wordpress/i18n';
import moment from 'moment';
import { TableCard, Link } from '@woocommerce/components';
import { onQueryChange, getQuery } from '@woocommerce/navigation';
import {
	downloadCSVFile,
	generateCSVDataFromTable,
	generateCSVFileName,
} from '@woocommerce/csv-export';
import classNames from 'classnames';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { useDisputes, useDisputesSummary } from 'data/index';
import OrderLink from 'components/order-link';
import DisputeStatusChip from 'components/dispute-status-chip';
import ClickableCell from 'components/clickable-cell';
import DetailsLink, { getDetailsURL } from 'components/details-link';
import Page from 'components/page';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import { reasons } from './strings';
import { formatStringValue } from 'utils';
import { formatExplicitCurrency } from 'utils/currency';
import DisputesFilters from './filters';
import DownloadButton from 'components/download-button';
import disputeStatusMapping from 'components/dispute-status-chip/mappings';
import { DisputesTableHeader } from 'wcpay/types/disputes';
import { getDisputesCSV } from 'wcpay/data/disputes/resolvers';
import { applyThousandSeparator } from '../utils/index.js';

import './style.scss';

const getHeaders = ( sortColumn?: string ): DisputesTableHeader[] => [
	{
		key: 'details',
		label: '',
		required: true,
		cellClassName: classNames( 'info-button', {
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
		required: true,
		isLeftAligned: true,
		isSortable: true,
		defaultSort: true,
		defaultOrder: 'desc',
	},
	{
		key: 'dueBy',
		label: __( 'Respond by', 'woocommerce-payments' ),
		screenReaderLabel: __( 'Respond by', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
		isSortable: true,
	},
];

export const DisputesList = (): JSX.Element => {
	const [ isDownloading, setIsDownloading ] = useState( false );
	const { createNotice } = useDispatch( 'core/notices' );
	const { disputes, isLoading } = useDisputes( getQuery() );

	const { disputesSummary, isLoading: isSummaryLoading } = useDisputesSummary(
		getQuery()
	);

	const headers = getHeaders( getQuery().orderby );
	const totalRows = disputesSummary.count || 0;

	const rows = disputes.map( ( dispute ) => {
		const clickable = ( children: React.ReactNode ): JSX.Element => (
			<ClickableCell
				href={ getDetailsURL( dispute.dispute_id, 'disputes' ) }
			>
				{ children }
			</ClickableCell>
		);

		const detailsLink = (
			<DetailsLink id={ dispute.dispute_id } parentSegment="disputes" />
		);

		const reasonMapping = reasons[ dispute.reason ];
		const reasonDisplay = reasonMapping
			? reasonMapping.display
			: formatStringValue( dispute.reason );

		const data: {
			[ key: string ]: {
				value: number | string;
				display: JSX.Element;
			};
		} = {
			amount: {
				value: dispute.amount / 100,
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
				value: dispute.reason,
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
					dateI18n(
						'M j, Y',
						moment( dispute.created ).toISOString()
					)
				),
			},
			dueBy: {
				value: dispute.due_by,
				display: clickable(
					dateI18n(
						'M j, Y / g:iA',
						moment.utc( dispute.due_by ).local().toISOString()
					)
				),
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
		};
		return headers.map(
			( { key } ) => data[ key ] || { value: undefined, display: null }
		);
	} );

	const downloadable = !! rows.length;

	const onDownload = async () => {
		setIsDownloading( true );
		const title = __( 'Disputes', 'woocommerce-payments' );
		const downloadType = totalRows > rows.length ? 'endpoint' : 'browser';
		const userEmail = wcpaySettings.currentUserEmail;

		if ( 'endpoint' === downloadType ) {
			const {
				date_before: dateBefore,
				date_after: dateAfter,
				date_between: dateBetween,
				match,
				status_is: statusIs,
				status_is_not: statusIsNot,
			} = getQuery();

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
				try {
					const {
						exported_disputes: exportedDisputes,
					} = await apiFetch( {
						path: getDisputesCSV( {
							userEmail,
							dateAfter,
							dateBefore,
							dateBetween,
							match,
							statusIs,
							statusIsNot,
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

					wcpayTracks.recordEvent( 'wcpay_disputes_download', {
						exported_disputes: exportedDisputes,
						total_disputes: exportedDisputes,
						download_type: 'endpoint',
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
			const csvColumns = [
				{
					...headers[ 0 ],
					label: __( 'Dispute Id', 'woocommerce-payments' ),
				},
				...headers.slice( 1 ),
			];

			const csvRows = rows.map( ( row ) => {
				return [
					...row.slice( 0, 3 ),
					{
						...row[ 3 ],
						value:
							disputeStatusMapping[ row[ 3 ].value ?? '' ]
								.message,
					},
					{
						...row[ 4 ],
						value: formatStringValue(
							( row[ 4 ].value ?? '' ).toString()
						),
					},
					...row.slice( 5, 10 ),
					{
						...row[ 10 ],
						value: dateI18n(
							'Y-m-d',
							moment( row[ 10 ].value ).toISOString()
						),
					},
					{
						...row[ 11 ],
						value: dateI18n(
							'Y-m-d / g:iA',
							moment( row[ 11 ].value ).toISOString()
						),
					},
				];
			} );

			downloadCSVFile(
				generateCSVFileName( title, getQuery() ),
				generateCSVDataFromTable( csvColumns, csvRows )
			);

			wcpayTracks.recordEvent( 'wcpay_disputes_download', {
				exported_disputes: csvRows.length,
				total_disputes: disputesSummary.count,
				download_type: 'browser',
			} );
		}

		setIsDownloading( false );
	};

	let summary;
	const isDisputesSummaryDataLoaded =
		disputesSummary.count !== undefined && false === isSummaryLoading;
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
			<TestModeNotice topic={ topics.disputes } />
			<DisputesFilters storeCurrencies={ storeCurrencies } />
			<TableCard
				className="wcpay-disputes-list"
				title={ __( 'Disputes', 'woocommerce-payments' ) }
				isLoading={ isLoading }
				rowsPerPage={ parseInt( getQuery().per_page ?? '', 10 ) || 25 }
				totalRows={ totalRows }
				headers={ headers }
				rows={ rows }
				summary={ summary }
				query={ getQuery() }
				onQueryChange={ onQueryChange }
				actions={ [
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

export default DisputesList;
