/** @format **/

/**
 * External dependencies
 */
import { flatMap, find } from 'lodash';
import Gridicon from 'gridicons';
import Currency, { getCurrencyData } from '@woocommerce/currency';
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import { addQueryArgs } from '@wordpress/url';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';

/**
 * Internal dependencies
 */
import { reasons as disputeReasons } from 'disputes/strings';

const currencyData = getCurrencyData();

/**
 * Gets wc-admin Currency for the given currency code
 *
 * @param {String} currencyCode Currency code
 *
 * @return {Currency} Currency object
 */
const getCurrency = ( currencyCode ) => {
	const currency = find( currencyData, { code: currencyCode } );
	if ( currency ) {
		return new Currency( currency );
	}
	return new Currency();
};

/**
 * Creates a Gridicon
 *
 * @param {String} icon Icon to render
 * @param {String} className Extra class name, defaults to empty
 *
 * @return {Gridicon} Gridicon component
 */
const getIcon = ( icon, className = '' ) => <Gridicon icon={ icon } className={ className } />;

/**
 * Creates a timeline item about a payment status change
 *
 * @param {Object} event An event triggering the status change
 * @param {String} status Localized status description
 *
 * @return {Object} Formatted status change timeline item
 */
const getStatusChangeTimelineItem = ( event, status ) => {
	return {
		date: new Date( event.datetime * 1000 ),
		icon: getIcon( 'sync' ),
		headline: sprintf(
			// translators: %s new status, for example Authorized, Refunded, etc
			__( 'Payment status changed to %s', 'woocommerce-payments' ),
			status
		),
		body: [],
		hideTimestamp: true,
	};
};

/**
 * Creates a timeline item about a deposit
 *
 * @param {Object} event An event affecting the deposit
 * @param {Number} formattedAmount Formatted amount
 * @param {Boolean} isPositive Whether the amount will be added or deducted
 * @param {Array} body Any extra subitems that should be included as item body
 *
 * @return {Object} Deposit timeline item
 */
const getDepositTimelineItem = ( event, formattedAmount, isPositive, body = [] ) => {
	let headline = '';
	if ( event.deposit ) {
		headline = sprintf(
			isPositive
				// translators: %1$s - formatted amount, %2$s - deposit arrival date, <a> - link to the deposit
				? __( '%1$s was added to your <a>%2$s deposit</a>', 'woocommerce-payments' )
				// translators: %1$s - formatted amount, %2$s - deposit arrival date, <a> - link to the deposit
				: __( '%1$s was deducted from your <a>%2$s deposit</a>', 'woocommerce-payments' ),
			formattedAmount,
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
			formattedAmount
		);
	}

	return {
		date: new Date( event.datetime * 1000 ),
		icon: getIcon( isPositive ? 'plus' : 'minus' ),
		headline,
		body,
		hideTimestamp: true,
	};
};

/**
 * Formats the main item for the event
 *
 * @param {Object} event Event object
 * @param {String|Object} headline Headline describing the event
 * @param {String} icon Icon to render for this event
 * @param {String} iconClass Icon class
 * @param {Array} body Body to include in this item, defaults to empty
 *
 * @return {Object} Formatted main item
 */
const getMainTimelineItem = ( event, headline, icon, iconClass, body = [] ) => ( {
	date: new Date( event.datetime * 1000 ),
	headline,
	icon: getIcon( icon, iconClass ),
	body,
} );

/**
 * Formats an event into one or more payment timeline items
 *
 * @param {Object} event An event data
 *
 * @return {Array} Payment timeline items
 */
const mapEventToTimelineItems = ( event ) => {
	const { type } = event;

	const currency = getCurrency( event.currency || 'USD' );
	const formatCurrency = ( amount ) => currency.formatCurrency( Math.abs( amount / 100 ) );
	const stringWithAmount = ( headline, amount ) => sprintf( headline, formatCurrency( amount ) );

	if ( 'authorized' === type ) {
		return [
			getMainTimelineItem(
				event,
				stringWithAmount( __( 'A payment of %s was successfully authorized', 'woocommerce-payments' ), event.amount ),
				'checkmark',
				'is-warning'
			),
			getStatusChangeTimelineItem( event, __( 'Authorized', 'woocommerce-payments' ) ),
		];
	} else if ( 'authorization_voided' === type ) {
		return [
			getMainTimelineItem(
				event,
				stringWithAmount( __( 'Authorization for %s was voided', 'woocommerce-payments' ), event.amount ),
				'checkmark',
				'is-warning'
			),
			getStatusChangeTimelineItem( event, __( 'Authorization Voided', 'woocommerce-payments' ) ),
		];
	} else if ( 'authorization_expired' === type ) {
		return [
			getMainTimelineItem(
				event,
				stringWithAmount( __( 'Authorization for %s expired', 'woocommerce-payments' ), event.amount ),
				'cross',
				'is-error'
			),
			getStatusChangeTimelineItem( event, __( 'Authorization Expired', 'woocommerce-payments' ) ),
		];
	} else if ( 'captured' === type ) {
		const formattedNet = formatCurrency( event.amount - event.fee );
		return [
			getMainTimelineItem(
				event,
				stringWithAmount( __( 'A payment of %s was successfully charged', 'woocommerce-payments' ), event.amount ),
				'checkmark',
				'is-success',
				[
					stringWithAmount( __( 'Fee: %s', 'woocommerce-payments' ), event.fee ),
					sprintf( __( 'Net deposit: %s', 'woocommerce-payments' ), formattedNet ),
				]
			),
			getDepositTimelineItem( event, formattedNet, true ),
			getStatusChangeTimelineItem( event, __( 'Paid', 'woocommerce-payments' ) ),
		];
	} else if ( 'partial_refund' === type || 'full_refund' === type ) {
		const formattedAmount = formatCurrency( event.amount_refunded );
		return [
			getMainTimelineItem(
				event,
				sprintf(
					__( 'A payment of %s was successfully refunded', 'woocommerce-payments' ),
					formattedAmount
				),
				'checkmark',
				'is-success'
			),
			getDepositTimelineItem( event, formattedAmount, false ),
			getStatusChangeTimelineItem( event, 'full_refund' === type
				? __( 'Refunded', 'woocommerce-payments' )
				: __( 'Partial Refund', 'woocommerce-payments' )
			),
		];
	} else if ( 'failed' === type ) {
		return [
			getMainTimelineItem(
				event,
				stringWithAmount( __( 'A payment of %s failed', 'woocommerce-payments' ), event.amount ),
				'cross',
				'is-error'
			),
			getStatusChangeTimelineItem( event, __( 'Failed', 'woocommerce-payments' ) ),
		];
	} else if ( 'dispute_needs_response' === type ) {
		let reasonHeadline = __( 'Payment disputed', 'woocommerce-payments' );
		if ( disputeReasons[ event.reason ] ) {
			reasonHeadline = sprintf(
				__( 'Payment disputed as %s', 'woocommerce-payments' ),
				disputeReasons[ event.reason ].display,
			);
		}

		const disputeUrl = addQueryArgs(
			'admin.php',
			{
				page: 'wc-admin',
				path: '/payments/disputes/details',
				id: event.dispute_id,
			}
		);

		let depositTimelineItem;
		if ( null === event.amount ) {
			depositTimelineItem = {
				date: new Date( event.datetime * 1000 ),
				icon: getIcon( 'info-outline' ),
				headline: __( 'No funds have been withdrawn yet', 'woocommerce-payments' ),
				body: [
					__(
						"The cardholder's bank is requesting more information to decide whether to return these funds to the cardholder.",
						'woocommerce-services'
					),
				],
				hideTimestamp: true,
			};
		} else {
			const formattedTotal = formatCurrency( Math.abs( event.amount ) + Math.abs( event.fee ) );
			depositTimelineItem = getDepositTimelineItem( event, formattedTotal, false, [
				stringWithAmount( __( 'Disputed amount: %s', 'woocommerce-payments' ), event.amount ),
				stringWithAmount( __( 'Fee: %s', 'woocommerce-payments' ), event.fee ),
			] );
		}

		return [
			getMainTimelineItem(
				event,
				reasonHeadline,
				'cross',
				'is-error',
				[ <a href={ disputeUrl }>{ __( 'View dispute', 'woocommerce-payments' ) }</a> ]
			),
			depositTimelineItem,
			getStatusChangeTimelineItem( event, __( 'Disputed: Needs Response', 'woocommerce-payments' ) ),
		];
	} else if ( 'dispute_in_review' === type ) {
		return [
			getMainTimelineItem( event, __( 'Challenge evidence submitted', 'woocommerce-payments' ), 'checkmark', 'is-success' ),
			getStatusChangeTimelineItem( event, __( 'Disputed: In Review', 'woocommerce-payments' ) ),
		];
	} else if ( 'dispute_won' === type ) {
		const formattedTotal = formatCurrency( Math.abs( event.amount ) + Math.abs( event.fee ) );
		return [
			getMainTimelineItem(
				event,
				__( 'Dispute won! The bank ruled in your favor', 'woocommerce-payments' ),
				'notice-outline',
				'is-success'
			),
			getDepositTimelineItem( event, formattedTotal, true, [
				stringWithAmount( __( 'Disputed amount: %s', 'woocommerce-payments' ), event.amount ),
				stringWithAmount( __( 'Fee: %s', 'woocommerce-payments' ), event.fee ),
			] ),
			getStatusChangeTimelineItem( event, __( 'Disputed: Won', 'woocommerce-payments' ) ),
		];
	} else if ( 'dispute_lost' === type ) {
		return [
			getMainTimelineItem(
				event,
				__( 'Dispute lost. The bank ruled favor of your customer', 'woocommerce-payments' ),
				'cross',
				'is-error'
			),
			getStatusChangeTimelineItem( event, __( 'Disputed: Lost', 'woocommerce-payments' ) ),
		];
	} else if ( 'dispute_warning_closed' === type ) {
		return [
			getMainTimelineItem(
				event,
				__( 'Dispute inquiry closed. The bank chose not to pursue this dispute.', 'woocommerce-payments' ),
				'notice-outline',
				'is-success'
			),
		];
	} else if ( 'dispute_charge_refunded' === type ) {
		return [
			getMainTimelineItem(
				event,
				__( 'The disputed charge has been refunded.', 'woocommerce-payments' ),
				'notice-outline',
				'is-success'
			),
		];
	}

	return [];
};

/**
 * Maps the timeline events coming from the server to items that can be used in Timeline component
 *
 * @param {Array} timelineEvents array of events
 *
 * @return {Array} Array of view items
 */
export default ( timelineEvents ) => {
	if ( ! timelineEvents ) {
		return [];
	}

	return flatMap( timelineEvents, mapEventToTimelineItems );
};
