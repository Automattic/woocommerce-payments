/**
 * External dependencies
 */
import React from 'react';
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import moment from 'moment';
import { TableCardColumn, TableCardBodyColumn } from '@woocommerce/components';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { getDetailsURL } from 'components/details-link';
import ClickableCell from 'components/clickable-cell';
import { formatExplicitCurrency } from 'utils/currency';
import wcpayTracks from 'tracks';
import TransactionStatusChip from 'components/transaction-status-chip';
import { FraudOutcomeTransaction } from '../../data';

interface Column extends TableCardColumn {
	key: 'created' | 'amount' | 'customer' | 'status';
	visible?: boolean;
	cellClassName?: string;
}

const rowDataFallback: TableCardBodyColumn = {
	display: null,
};

export const getRiskReviewListColumns = (): Column[] =>
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
		{
			key: 'action',
			label: '',
			screenReaderLabel: '',
			visible: true,
			required: true,
			isNumeric: true,
		},
	].filter( Boolean ) as Column[]; // We explicitly define the type because TypeScript can't infer the type post-filtering.

export const getRiskReviewListRowContent = (
	data: FraudOutcomeTransaction
): Record< string, TableCardBodyColumn > => {
	const detailsURL = getDetailsURL( data.payment_intent.id, 'transactions' );
	const formattedCreatedDate = dateI18n(
		'M j, Y / g:iA',
		moment.utc( data.created ).local().toISOString()
	);

	const clickable = ( children: JSX.Element | string ) => (
		<ClickableCell href={ detailsURL }>{ children }</ClickableCell>
	);

	const handleActionButtonClick = () => {
		wcpayTracks.recordEvent(
			'payments_transactions_risk_review_list_review_button_click',
			{
				payment_intent_id: data.payment_intent.id,
			}
		);
	};

	return {
		status: {
			value: data.status,
			display: <TransactionStatusChip status="review" />,
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
		action: {
			display: (
				<Button
					isSecondary
					href={ detailsURL }
					onClick={ handleActionButtonClick }
				>
					{ __( 'Review' ) }
				</Button>
			),
		},
	};
};

export const getRiskReviewListColumnsStructure = (
	data: FraudOutcomeTransaction,
	columns: Column[]
): TableCardBodyColumn[] => {
	const content = getRiskReviewListRowContent( data );

	return columns.map( ( { key } ) => content[ key ] || rowDataFallback );
};
