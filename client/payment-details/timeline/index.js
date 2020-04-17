/** @format **/

/**
 * External dependencies
 */
import { flatMap, find } from 'lodash';
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import Currency, { getCurrencyData } from '@woocommerce/currency';
import { Card, Timeline } from '@woocommerce/components';
import { addQueryArgs } from '@wordpress/url';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';

/**
 * Internal dependencies
 */
import { useTimeline } from 'data';
import { reasons as disputeReasons } from 'disputes/strings';
import Loadable, { LoadableBlock } from 'components/loadable';

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
			isPositive
				// translators: %1$s - formatted amount, %2$s - deposit arrival date, <a> - link to the deposit
				? __( '%1$s was added to your <a>%2$s deposit</a>', 'woocommerce-payments' )
				// translators: %1$s - formatted amount, %2$s - deposit arrival date, <a> - link to the deposit
				: __( '%1$s was deducted from your <a>%2$s deposit</a>', 'woocommerce-payments' ),
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
			isPositive
				// translators: %s - formatted amount
				? __( '%s will be added to a future deposit', 'woocommerce-payments' )
				// translators: %s - formatted amount
				: __( '%s will be deducted from a future deposit', 'woocommerce-payments' ),
			currency.formatCurrency( amount / 100 )
		);
	}

	return {
		datetime: event.datetime,
		gridicon: isPositive ? 'plus' : 'minus',
		headline,
		body,
	};
};

const getStatusChangeTimelineItem = ( event, status ) => {
	return {
		datetime: event.datetime,
		gridicon: 'sync',
		headline: sprintf(
			// translators: %s new status, for example Authorized, Refunded, etc
			__( 'Payment status changed to %s', 'woocommerce-payments' ),
			status
		),
		body: [],
	};
};

const mapEventToTimelineItems = ( event ) => {
	const { datetime, type } = event;
	const baseItem = {
		datetime,
		body: [],
	};
	const currency = getCurrency( event.currency || 'USD' );

	if ( 'authorized' === type ) {
		return [
			{
				...baseItem,
				gridicon: 'checkmark',
				headline: sprintf(
					__( 'A payment of %s was successfully authorized', 'woocommerce-payments' ),
					currency.formatCurrency( event.amount / 100 )
				),
			},
			getStatusChangeTimelineItem( event, __( 'Authorized', 'woocommerce-payments' ) ),
		];
	} else if ( 'authorization_voided' === type ) {
		return [
			{
				...baseItem,
				gridicon: 'checkmark',
				headline: sprintf(
					__( 'Authorization for %s was voided', 'woocommerce-payments' ),
					currency.formatCurrency( event.amount / 100 )
				),
			},
			getStatusChangeTimelineItem( event, __( 'Authorization Voided', 'woocommerce-payments' ) ),
		];
	} else if ( 'authorization_expired' === type ) {
		return [
			{
				...baseItem,
				gridicon: 'cross',
				headline: sprintf(
					__( 'Authorization for %s expired', 'woocommerce-payments' ),
					currency.formatCurrency( event.amount / 100 )
				),
			},
			getStatusChangeTimelineItem( event, __( 'Authorization Expired', 'woocommerce-payments' ) ),
		];
	} else if ( 'captured' === type ) {
		const net = event.amount - event.fee;
		return [
			{
				...baseItem,
				gridicon: 'checkmark',
				headline: sprintf(
					__( 'A payment of %s was successfully charged', 'woocommerce-payments' ),
					currency.formatCurrency( event.amount / 100 )
				),
				body: [
					sprintf(
						__( 'Fee: %s', 'woocommerce-payments' ),
						currency.formatCurrency( event.fee / 100 )
					),
					sprintf(
						__( 'Net deposit: %s', 'woocommerce-payments' ),
						currency.formatCurrency( net / 100 )
					),
				],
			},
			getDepositTimelineItem( event, net, currency, true ),
			getStatusChangeTimelineItem( event, __( 'Paid', 'woocommerce-payments' ) ),
		];
	} else if ( 'partial_refund' === type || 'full_refund' === type ) {
		return [
			{
				...baseItem,
				gridicon: 'checkmark',
				headline: sprintf(
					__( 'A payment of %s was successfully refunded', 'woocommerce-payments' ),
					currency.formatCurrency( event.amount_refunded / 100 )
				),
			},
			getDepositTimelineItem( event, event.amount_refunded, currency, false ),
			getStatusChangeTimelineItem( event, 'full_refund' === type
				? __( 'Refunded', 'woocommerce-payments' )
				: __( 'Partial Refund', 'woocommerce-payments' )
			),
		];
	} else if ( 'failed' === type ) {
		return [
			{
				...baseItem,
				gridicon: 'cross',
				headline: sprintf(
					__( 'A payment of %s failed', 'woocommerce-payments' ),
					currency.formatCurrency( event.amount / 100 )
				),
				body: [
					event.reason,
				],
			},
			getStatusChangeTimelineItem( event, __( 'Failed', 'woocommerce-payments' ) ),
		];
	} else if ( 'dispute_needs_response' === type ) {
		const total = Math.abs( event.amount ) + event.fee;
		let reasonHeadline = __( 'Payment disputed', 'woocommerce-payments' );
		if ( disputeReasons[ event.reason ] ) {
			reasonHeadline = sprintf(
				__( 'Payment disputed as %s', 'woocommerce-payments' ),
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
				sprintf( __( 'Disputed amount: %s', 'woocommerce-payments' ), currency.formatCurrency( Math.abs( event.amount ) / 100 ) ),
				sprintf( __( 'Fee: %s', 'woocommerce-payments' ), currency.formatCurrency( event.fee / 100 ) ),
			] ),
			getStatusChangeTimelineItem( event, __( 'Disputed: Needs Response', 'woocommerce-payments' ) ),
		];
	} else if ( 'dispute_in_review' === type ) {
		return [
			{
				...baseItem,
				gridicon: 'checkmark',
				headline: __( 'Challenge evidence submitted', 'woocommerce-payments' ),
			},
			getStatusChangeTimelineItem( event, __( 'Disputed: In Review', 'woocommerce-payments' ) ),
		];
	} else if ( 'dispute_won' === type ) {
		const total = event.amount + event.fee;
		return [
			{
				...baseItem,
				gridicon: 'notice-outline',
				headline: __( 'Dispute won! The bank ruled in your favor', 'woocommerce-payments' ),
			},
			getDepositTimelineItem( event, total, currency, true, [
				sprintf( __( 'Disputed amount: %s', 'woocommerce-payments' ), currency.formatCurrency( event.amount / 100 ) ),
				sprintf( __( 'Fee: %s', 'woocommerce-payments' ), currency.formatCurrency( event.fee / 100 ) ),
			] ),
			getStatusChangeTimelineItem( event, __( 'Disputed: Won', 'woocommerce-payments' ) ),
		];
	} else if ( 'dispute_lost' === type ) {
		return [
			{
				...baseItem,
				gridicon: 'cross',
				headline: __( 'Dispute lost. The bank ruled favor of your customer', 'woocommerce-payments' ),
			},
			getStatusChangeTimelineItem( event, __( 'Disputed: Lost', 'woocommerce-payments' ) ),
		];
	}

	return [];
};

const PaymentDetailsTimeline = ( props ) => {
	const charge = props.charge;
	let isLoading = props.isLoading;
	let timeline;

	if ( ! isLoading ) {
		( { timeline, isLoading } = useTimeline( charge.payment_intent ) );
	}

	if ( isLoading ) {
		return (
			<Card title={ <Loadable isLoading value={ __( 'Timeline', 'woocommerce-payments' ) } /> }>
				<LoadableBlock isLoading numLines={ 3 } />
				<LoadableBlock isLoading numLines={ 3 } />
				<LoadableBlock isLoading numLines={ 3 } />
			</Card>
		);
	}

	const items = flatMap( timeline, mapEventToTimelineItems );

	return <Timeline items={ items } />;
};

export default PaymentDetailsTimeline;
