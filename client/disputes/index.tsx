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
import DisputesFilters from './filters';
import DownloadButton from 'components/download-button';
import disputeStatusMapping from 'components/dispute-status-chip/mappings';
import { DisputesTableHeader } from 'wcpay/types/disputes';
import { getPostUrl } from 'wcpay/utils';

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
	/* eslint-disable */
	const { disputes, isLoading } = useDisputes( getQuery() );

	const {
		disputesSummary,
		isLoading: isSummaryLoading,
	} = useDisputesSummary();

	const rows = disputes.map( ( dispute ) => {
		const {
			dispute_id,
			amount = 0,
			currency,
			reason,
			source,
			order_number,
			customer_name,
			customer_email,
			customer_country,
			status,
			created,
			due_by,
		} = dispute;

		const orderObject = {
			number: order_number,
			url: getPostUrl( {
				post: order_number,
				action: 'edit',
			} ),
		};

		const order = {
			value: order_number,
			display: <OrderLink order={ orderObject } />,
		};

		const clickable = ( children: React.ReactNode ): JSX.Element => (
			<ClickableCell href={ getDetailsURL( dispute_id, 'disputes' ) }>
				{ children }
			</ClickableCell>
		);

		const detailsLink = (
			<DetailsLink id={ dispute_id } parentSegment="disputes" />
		);

		const reasonMapping = reasons[ reason ];
		const reasonDisplay = reasonMapping
			? reasonMapping.display
			: formatStringValue( reason );

		const data = {
			amount: {
				value: amount / 100,
				display: clickable(
					formatExplicitCurrency( amount, currency )
				),
			},
			status: {
				value: status,
				display: clickable( <DisputeStatusChip status={ status } /> ),
			},
			reason: {
				value: reason,
				display: clickable( reasonDisplay ),
			},
			source: {
				value: source,
				display: clickable(
					<span
						className={ `payment-method__brand payment-method__brand--${ source }` }
					/>
				),
			},
			created: {
				value: created,
				display: clickable(
					dateI18n( 'M j, Y', moment( created ).toISOString() )
				),
			},
			dueBy: {
				value: due_by,
				display: clickable(
					dateI18n( 'M j, Y / g:iA', moment( due_by ).toISOString() )
				),
			},
			order,
			customer: {
				value: customer_name,
				display: clickable( customer_name ),
			},
			email: {
				value: customer_email,
				display: clickable( customer_email ),
			},
			country: {
				value: customer_country,
				display: clickable( customer_country ),
			},
			details: { value: dispute_id, display: detailsLink },
		};
		return headers.map( ( { key } ) => data[ key ] || { display: null } );
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
					value:
						typeof row[ 3 ].value === 'string'
							? formatStringValue( row[ 3 ].value )
							: '',
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

	const isCurrencyFiltered = 'string' === typeof getQuery().store_currency_is;

	const storeCurrencies =
		disputesSummary.store_currencies ||
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
