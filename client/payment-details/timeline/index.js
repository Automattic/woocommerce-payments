/** @format **/

/**
 * External dependencies
 */
import { flatMap, find } from 'lodash';
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import Currency, { getCurrencyData } from '@woocommerce/currency';
import { addQueryArgs } from '@wordpress/url';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';

/**
 * Internal dependencies
 */
import { useTimeline } from 'data';
import { Card, Timeline } from '@woocommerce/components';
import { reasons as disputeReasons } from 'disputes/strings';

const currencyData = getCurrencyData();

const getCurrency = ( currencyCode ) => {
	const currency = find( currencyData, { code: currencyCode } );
	if ( currency ) {
		return new Currency( currency );
	}
	return new Currency();
};

const getDepositTimelineItem = ( event, amount, currency, isPositive, body = [] ) => {
	let headline = '';
	if ( event.deposit ) {
		headline = sprintf(
			// translators: %1$s - amount, %2$s - deposit arrival date, <a> - link to the deposit
			isPositive ? __( '%1$s was added to your <a>%2$s deposit</a>' ) : __( '%1$s was deducted from your <a>%2$s deposit</a>' ),
			currency.formatCurrency( amount / 100 ),
			dateI18n( 'M j, Y', moment( event.deposit.arrival_date * 1000 ) )
		);

		const depositUrl = addQueryArgs(
			'admin.php',
			{
				page: 'wc-admin',
				path: '/payments/deposits/details',
				id: event.deposit.id,
			}
		);

		headline = createInterpolateElement( headline, {
			// eslint-disable-next-line jsx-a11y/anchor-has-content
			a: <a href={ depositUrl } />,
		} );
	} else {
		headline = sprintf(
			// translators: %s - amount
			isPositive ? __( '%s will be added to a future deposit' ) : __( '%s will be deducted from a future deposit' ),
			currency.formatCurrency( amount / 100 )
		);
	}

	return {
		datetime: event.date,
		gridicon: isPositive ? 'plus' : 'minus',
		headline,
		body,
	};
};

const getStatusChangeTimelineItem = ( event, status ) => {
	return {
		datetime: event.date,
		gridicon: 'sync',
		headline: sprintf(
			// translators: %s new status, for example Authorized, Refunded, etc
			__( 'Payment status changed to %s' ),
			status
		),
		body: [],
	};
};

const mapEventToTimelineItems = ( event ) => {
	const { date, type } = event;
	const baseItem = {
		datetime: date,
		body: [],
	};
	const currency = getCurrency( event.currency || 'USD' );

	if ( 'authorized' === type ) {
		return [
			{
				...baseItem,
				gridicon: 'checkmark',
				headline: sprintf(
					__( 'A payment of %s was successfully authorized' ),
					currency.formatCurrency( event.amount / 100 )
				),
			},
			getStatusChangeTimelineItem( event, __( 'Authorized' ) ),
		];
	} else if ( 'authorization_voided' === type ) {
		return [
			{
				...baseItem,
				gridicon: 'checkmark',
				headline: sprintf(
					__( 'Authorization for %s was voided' ),
					currency.formatCurrency( event.amount / 100 )
				),
			},
			getStatusChangeTimelineItem( event, __( 'Authorization Voided' ) ),
		];
	} else if ( 'authorization_expired' === type ) {
		return [
			{
				...baseItem,
				gridicon: 'cross',
				headline: sprintf(
					__( 'Authorization for %s expired' ),
					currency.formatCurrency( event.amount / 100 )
				),
			},
			getStatusChangeTimelineItem( event, __( 'Authorization Expired' ) ),
		];
	} else if ( 'captured' === type ) {
		const net = event.amount - event.fee;
		return [
			{
				...baseItem,
				gridicon: 'checkmark',
				headline: sprintf(
					__( 'A payment of %s was successfully charged' ),
					currency.formatCurrency( event.amount / 100 )
				),
				body: [
					sprintf(
						__( 'Fee: %s' ),
						currency.formatCurrency( event.fee / 100 )
					),
					sprintf(
						__( 'Net deposit: %s' ),
						currency.formatCurrency( net / 100 )
					),
				],
			},
			getDepositTimelineItem( event, net, currency, true ),
			getStatusChangeTimelineItem( event, __( 'Paid' ) ),
		];
	} else if ( 'partial_refund' === type || 'full_refund' === type ) {
		return [
			{
				...baseItem,
				gridicon: 'checkmark',
				headline: sprintf(
					__( 'A payment of %s was successfully refunded' ),
					currency.formatCurrency( event.amount_refunded / 100 )
				),
			},
			getDepositTimelineItem( event, event.amount_refunded, currency, false ),
			getStatusChangeTimelineItem( event, 'full_refund' === type ? __( 'Refunded' ) : __( 'Partial Refund' ) ),
		];
	} else if ( 'failed' === type ) {
		return [
			{
				...baseItem,
				gridicon: 'cross',
				headline: sprintf(
					__( 'A payment of %s failed' ),
					currency.formatCurrency( event.amount / 100 )
				),
			},
			getStatusChangeTimelineItem( event, __( 'Failed' ) ),
		];
	} else if ( 'dispute_needs_response' === type ) {
		const total = Math.abs( event.amount ) + event.fee;
		let reasonHeadline = __( 'Payment disputed' );
		if ( disputeReasons[ event.reason ] ) {
			reasonHeadline = sprintf(
				__( 'Payment disputed as %s' ),
				disputeReasons[ event.reason ].display,
			);
		}

		return [
			{
				...baseItem,
				gridicon: 'cross',
				headline: reasonHeadline,
			},
			getDepositTimelineItem( event, total, currency, false, [
				sprintf( __( 'Disputed amount: %s' ), currency.formatCurrency( Math.abs( event.amount ) / 100 ) ),
				sprintf( __( 'Fee: %s' ), currency.formatCurrency( event.fee / 100 ) ),
			] ),
			getStatusChangeTimelineItem( event, __( 'Disputed: Needs Response' ) ),
		];
	} else if ( 'dispute_in_review' === type ) {
		return [
			{
				...baseItem,
				gridicon: 'checkmark',
				headline: __( 'Challenge evidence submitted' ),
			},
			getStatusChangeTimelineItem( event, __( 'Disputed: In Review' ) ),
		];
	} else if ( 'dispute_won' === type ) {
		const total = event.amount + event.fee;
		return [
			{
				...baseItem,
				gridicon: 'notice-outline',
				headline: __( 'Dispute won! The bank ruled in your favor' ),
			},
			getDepositTimelineItem( event, total, currency, true, [
				sprintf( __( 'Disputed amount: %s' ), currency.formatCurrency( event.amount / 100 ) ),
				sprintf( __( 'Fee: %s' ), currency.formatCurrency( event.fee / 100 ) ),
			] ),
			getStatusChangeTimelineItem( event, __( 'Disputed: Won' ) ),
		];
	} else if ( 'dispute_lost' === type ) {
		return [
			{
				...baseItem,
				gridicon: 'cross',
				headline: __( 'Dispute lost. The bank ruled favor of your customer' ),
			},
			getStatusChangeTimelineItem( event, __( 'Disputed: Lost' ) ),
		];
	}

	return [];
};

const PaymentDetailsTimeline = ( props ) => {
	const { charge, isLoading } = props;

	if ( isLoading ) {
		return (
			<div>loading charge...</div>
		);
	}

	const { timeline, isLoading: isTimelineLoading } = useTimeline( charge.payment_intent );

	if ( isTimelineLoading ) {
		return (
			<div>loading timeline...</div>
		);
	}

	const items = flatMap( timeline, mapEventToTimelineItems );

	// TODO: this is a placeholder card and does not require translation
	return (
		<Card title="Timeline">
			<Timeline items={ items } />
		</Card>
	);
};

export default PaymentDetailsTimeline;
