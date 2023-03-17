/**
 * External dependencies
 */
import React from 'react';
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import moment from 'moment';
import { TableCardColumn, TableCardBodyColumn } from '@woocommerce/components';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { formatExplicitCurrency } from 'utils/currency';
import { Authorization } from '../../types/authorizations';
import TransactionStatusChip from '../../components/transaction-status-chip';

interface Column extends TableCardColumn {
	key: 'created' | 'amount' | 'customer' | 'risk_level' | 'status';
	visible?: boolean;
	cellClassName?: string;
}

const rowDataFallback: TableCardBodyColumn = {
	display: null,
};

const getDetailsURL = ( id: string ) =>
	addQueryArgs( 'post.php', {
		post: id,
		action: 'edit',
	} );

export const getBlockedListColumns = (): Column[] =>
	[
		{
			key: 'created',
			label: __( 'Date / Time', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Date / Time', 'woocommerce-payments' ),
			required: true,
			isLeftAligned: true,
			defaultOrder: 'desc',
			cellClassName: 'date-time',
			isSortable: true,
			defaultSort: true,
		},
		{
			key: 'amount',
			label: __( 'Amount', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Amount', 'woocommerce-payments' ),
			isNumeric: true,
			isSortable: true,
		},
		{
			key: 'customer',
			label: __( 'Customer', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Customer', 'woocommerce-payments' ),
			visible: true,
			isLeftAligned: true,
		},
		{
			key: 'status',
			label: __( 'Status', 'woocommerce-payments' ),
			screenReaderLabel: __( 'Status', 'woocommerce-payments' ),
			visible: true,
			isLeftAligned: true,
		},
	].filter( Boolean ) as Column[]; // We explicitly define the type because TypeScript can't infer the type post-filtering.

export const getBlockedListRowContent = (
	data: Authorization
): Record< string, TableCardBodyColumn > => {
	const detailsURL = getDetailsURL( data.order_id );
	const formattedCreatedDate = dateI18n(
		'M j, Y / g:iA',
		moment.utc( data.created ).local().toISOString()
	);

	const clickable = ( children: JSX.Element | string ) => (
		<a
			href={ detailsURL }
			tabIndex={ -1 }
			className="woocommerce-table__clickable-cell"
		>
			{ children }
		</a>
	);

	return {
		status: {
			value: ( data as any ).status,
			display: (
				<TransactionStatusChip status={ ( data as any ).status } />
			),
		},
		created: {
			value: formattedCreatedDate,
			display: clickable( formattedCreatedDate ),
		},
		amount: {
			value: data.amount,
			display: clickable(
				formatExplicitCurrency( data.amount, data.currency )
			),
		},
		customer: {
			value: data.customer_name,
			display: clickable( data.customer_name ),
		},
	};
};

export const getBlockedListColumnsStructure = (
	data: Authorization,
	columns: Column[]
): TableCardBodyColumn[] => {
	const content = getBlockedListRowContent( data );

	return columns.map( ( { key } ) => content[ key ] || rowDataFallback );
};
