/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import wcpayTracks from 'tracks';
import { dateI18n } from '@wordpress/date';
import { _n, __ } from '@wordpress/i18n';
import moment from 'moment';
import { TableCard } from '@woocommerce/components';
import { onQueryChange, getQuery } from '@woocommerce/navigation';
import {
	downloadCSVFile,
	generateCSVDataFromTable,
	generateCSVFileName,
} from '@woocommerce/csv-export';

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
import DownloadButton from 'components/download-button';
import disputeStatusMapping from 'components/dispute-status-chip/mappings';
import { DisputesTableHeader } from 'wcpay/types/disputes';

import './style.scss';

const headers: DisputesTableHeader[] = [
	{
		key: 'details',
		label: '',
		required: true,
		cellClassName: 'info-button',
		isLeftAligned: true,
	},
	{
		key: 'amount',
		label: __( 'Amount', 'woocommerce-payments' ),
		required: true,
	},
	{
		key: 'status',
		label: __( 'Status', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
	},
	{
		key: 'reason',
		label: __( 'Reason', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
	},
	{
		key: 'source',
		label: __( 'Source', 'woocommerce-payments' ),
		required: true,
		cellClassName: 'is-center-aligned',
	},
	{
		key: 'order',
		label: __( 'Order #', 'woocommerce-payments' ),
		required: true,
	},
	{
		key: 'customer',
		label: __( 'Customer', 'woocommerce-payments' ),
		isLeftAligned: true,
	},
	{
		key: 'email',
		label: __( 'Email', 'woocommerce-payments' ),
		visible: false,
		isLeftAligned: true,
	},
	{
		key: 'country',
		label: __( 'Country', 'woocommerce-payments' ),
		visible: false,
		isLeftAligned: true,
	},
	{
		key: 'created',
		label: __( 'Disputed on', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
	},
	{
		key: 'dueBy',
		label: __( 'Respond by', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
	},
];

export const DisputesList = (): JSX.Element => {
	const { disputes, isLoading } = useDisputes( getQuery() );

	const {
		disputesSummary,
		isLoading: isSummaryLoading,
	} = useDisputesSummary();

	const rows = disputes.map( ( dispute ) => {
		const order = dispute.order
			? {
					value: dispute.order.number,
					display: <OrderLink order={ dispute.order } />,
			  }
			: null;

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

		const data = {
			amount: {
				value: dispute.amount / 100,
				display: clickable(
					formatExplicitCurrency(
						dispute.amount || 0,
						dispute.currency || 'USD'
					)
				),
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
				value: dispute.source,
				display: clickable(
					<span
						className={ `payment-method__brand payment-method__brand--${ dispute.source }` }
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
						moment( dispute.due_by ).toISOString()
					)
				),
			},
			order,
			customer: {
				value: dispute.customer_name,
				display: clickable( dispute.customer_name ),
			},
			email: {
				value: dispute.customer_email,
				display: clickable( dispute.customer_email ),
			},
			country: {
				value: dispute.customer_country,
				display: clickable( dispute.customer_country ),
			},
			details: { value: dispute.dispute_id, display: detailsLink },
		};
		return headers.map(
			( { key } ) => data[ key ] || { value: undefined, display: null }
		);
	} );

	const downloadable = !! rows.length;

	function onDownload() {
		const title = __( 'Disputes', 'woocommerce-payments' );

		const csvColumns = [
			{
				...headers[ 0 ],
				label: __( 'Dispute Id', 'woocommerce-payments' ),
			},
			...headers.slice( 1 ),
		];

		const csvRows = rows.map( ( row ) => {
			return [
				...row.slice( 0, 2 ),
				{
					...row[ 2 ],
					value: disputeStatusMapping[ row[ 2 ].value ?? '' ].message,
				},
				{
					...row[ 3 ],
					value: formatStringValue(
						( row[ 3 ].value ?? '' ).toString()
					),
				},
				...row.slice( 4, 9 ),
				{
					...row[ 9 ],
					value: dateI18n(
						'Y-m-d',
						moment( row[ 9 ].value ).toISOString()
					),
				},
				{
					...row[ 10 ],
					value: dateI18n(
						'Y-m-d / g:iA',
						moment( row[ 10 ].value ).toISOString()
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
		} );
	}

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
				value: `${ disputesSummary.count }`,
			},
		];
	}

	return (
		<Page>
			<TestModeNotice topic={ topics.disputes } />
			<TableCard
				className="wcpay-disputes-list"
				title={ __( 'Disputes', 'woocommerce-payments' ) }
				isLoading={ isLoading }
				rowsPerPage={ parseInt( getQuery().per_page ?? '', 10 ) || 25 }
				totalRows={ disputesSummary.count || 0 }
				headers={ headers }
				rows={ rows }
				summary={ summary }
				query={ getQuery() }
				onQueryChange={ onQueryChange }
				actions={ [
					downloadable && (
						<DownloadButton
							key="download"
							isDisabled={ isLoading }
							onClick={ onDownload }
						/>
					),
				] }
			/>
		</Page>
	);
};

export default DisputesList;
