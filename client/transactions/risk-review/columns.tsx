/**
 * External dependencies
 */
import React from 'react';
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import moment from 'moment';
import { TableCardColumn, TableCardBodyColumn } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import { getDetailsURL } from 'components/details-link';
import ClickableCell from 'components/clickable-cell';
import RiskLevel, { calculateRiskMapping } from 'components/risk-level';
import { formatExplicitCurrency } from 'utils/currency';
import CaptureAuthorizationButton from 'wcpay/components/capture-authorization-button';
import wcpayTracks from 'tracks';
import { Authorization } from '../../types/authorizations';
import TransactionStatusChip from 'wcpay/components/transaction-status-chip';

interface Column extends TableCardColumn {
	key: 'created' | 'amount' | 'customer' | 'risk_level' | 'status';
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
			key: 'risk_level',
			label: __( 'Risk level', 'woocommerce-payments' ),
			screenReaderLabel: __(
				'Risk level of transaction',
				'woocommerce-payments'
			),
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
		},
	].filter( Boolean ) as Column[]; // We explicitly define the type because TypeScript can't infer the type post-filtering.

export const getRiskReviewListRowContent = (
	data: Authorization
): Record< string, TableCardBodyColumn > => {
	const riskLevel = <RiskLevel risk={ data.risk_level } />;
	const detailsURL = getDetailsURL( data.payment_intent_id, 'transactions' );
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
				payment_intent_id: data.payment_intent_id,
			}
		);
	};

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
		risk_level: {
			value: calculateRiskMapping( data.risk_level ),
			display: clickable( riskLevel ),
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
				<CaptureAuthorizationButton
					orderId={ data.order_id }
					paymentIntentId={ data.payment_intent_id }
					onClick={ handleActionButtonClick }
				/>
			),
		},
	};
};

export const getRiskReviewListColumnsStructure = (
	data: Authorization,
	columns: Column[]
): TableCardBodyColumn[] => {
	const content = getRiskReviewListRowContent( data );

	return columns.map( ( { key } ) => content[ key ] || rowDataFallback );
};
