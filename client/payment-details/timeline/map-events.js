/** @format **/

/**
 * External dependencies
 */
import { flatMap } from 'lodash';
import Gridicon from 'gridicons';
import { __, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import moment from 'moment';
import { __experimentalCreateInterpolateElement as createInterpolateElement } from 'wordpress-element';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import { reasons as disputeReasons } from 'disputes/strings';
import {
	formatCurrency,
	formatFX,
	formatExplicitCurrency,
} from 'utils/currency';
import { formatFee } from 'utils/fees';
import { getAdminUrl } from 'wcpay/utils';

/**
 * Creates a Gridicon
 *
 * @param {string} icon Icon to render
 * @param {string} className Extra class name, defaults to empty
 *
 * @return {Gridicon} Gridicon component
 */
const getIcon = ( icon, className = '' ) => (
	<Gridicon icon={ icon } className={ className } />
);

/**
 * Creates a timeline item about a payment status change
 *
 * @param {Object} event An event triggering the status change
 * @param {string} status Localized status description
 *
 * @return {Object} Formatted status change timeline item
 */
const getStatusChangeTimelineItem = ( event, status ) => {
	return {
		date: new Date( event.datetime * 1000 ),
		icon: getIcon( 'sync' ),
		headline: sprintf(
			// translators: %s new status, for example Authorized, Refunded, etc
			__( 'Payment status changed to %s.', 'woocommerce-payments' ),
			status
		),
		body: [],
	};
};

/**
 * Creates a timeline item about a deposit
 *
 * @param {Object} event An event affecting the deposit
 * @param {string} formattedAmount Formatted amount string
 * @param {boolean} isPositive Whether the amount will be added or deducted
 * @param {Array} body Any extra subitems that should be included as item body
 *
 * @return {Object} Deposit timeline item
 */
const getDepositTimelineItem = (
	event,
	formattedAmount,
	isPositive,
	body = []
) => {
	let headline = '';
	if ( event.deposit ) {
		headline = sprintf(
			isPositive
				? // translators: %1$s - formatted amount, %2$s - deposit arrival date, <a> - link to the deposit
				  __(
						'%1$s was added to your <a>%2$s deposit</a>.',
						'woocommerce-payments'
				  )
				: // translators: %1$s - formatted amount, %2$s - deposit arrival date, <a> - link to the deposit
				  __(
						'%1$s was deducted from your <a>%2$s deposit</a>.',
						'woocommerce-payments'
				  ),
			formattedAmount,
			dateI18n(
				'M j, Y',
				moment( event.deposit.arrival_date * 1000 ).toISOString()
			)
		);

		const depositUrl = getAdminUrl( {
			page: 'wc-admin',
			path: '/payments/deposits/details',
			id: event.deposit.id,
		} );

		headline = createInterpolateElement( headline, {
			// eslint-disable-next-line jsx-a11y/anchor-has-content
			a: <Link href={ depositUrl } />,
		} );
	} else {
		headline = sprintf(
			isPositive
				? // translators: %s - formatted amount
				  __(
						'%s will be added to a future deposit.',
						'woocommerce-payments'
				  )
				: // translators: %s - formatted amount
				  __(
						'%s will be deducted from a future deposit.',
						'woocommerce-payments'
				  ),
			formattedAmount
		);
	}

	return {
		date: new Date( event.datetime * 1000 ),
		icon: getIcon( isPositive ? 'plus' : 'minus' ),
		headline,
		body,
	};
};

/**
 * Formats the main item for the event
 *
 * @param {Object} event Event object
 * @param {string | Object} headline Headline describing the event
 * @param {string} icon Icon to render for this event
 * @param {string} iconClass Icon class
 * @param {Array} body Body to include in this item, defaults to empty
 *
 * @return {Object} Formatted main item
 */
const getMainTimelineItem = (
	event,
	headline,
	icon,
	iconClass,
	body = []
) => ( {
	date: new Date( event.datetime * 1000 ),
	headline,
	icon: getIcon( icon, iconClass ),
	body,
} );

const isFXEvent = ( event = {} ) => {
	const { transaction_details: transactionDetails = {} } = event;
	const {
		customer_currency: customerCurrency,
		store_currency: storeCurrency,
	} = transactionDetails;
	return (
		customerCurrency && storeCurrency && customerCurrency !== storeCurrency
	);
};

/**
 * Returns a boolean indicating whether only fee applied is the base fee
 *
 * @param {Object} event Event object
 *
 * @return {boolean} true if the only applied fee is the base fee
 */
const isBaseFeeOnly = ( event ) => {
	if ( ! event.fee_rates ) return false;

	const history = event.fee_rates.history;
	return 1 === history?.length && 'base' === history[ 0 ].type;
};

const composeNetString = ( event ) => {
	if ( ! isFXEvent( event ) ) {
		return formatExplicitCurrency(
			event.amount - event.fee,
			event.currency
		);
	}

	return formatExplicitCurrency(
		event.transaction_details.store_amount -
			event.transaction_details.store_fee,
		event.transaction_details.store_currency
	);
};

const composeFeeString = ( event ) => {
	if ( ! event.fee_rates ) {
		return sprintf(
			/* translators: %s is a monetary amount */
			__( 'Fee: %s', 'woocommerce-payments' ),
			formatCurrency( event.fee, event.currency )
		);
	}

	const {
		percentage,
		fixed,
		fixed_currency: fixedCurrency,
		history,
	} = event.fee_rates;
	let feeAmount = event.fee;
	let feeCurrency = event.currency;

	if ( isFXEvent( event ) ) {
		feeAmount = event.transaction_details.store_fee;
		feeCurrency = event.transaction_details.store_currency;
	}

	const baseFeeLabel = isBaseFeeOnly( event )
		? __( 'Base fee', 'woocommerce-payments' )
		: __( 'Fee', 'woocommerce-payments' );

	if ( isBaseFeeOnly( event ) && history[ 0 ]?.capped ) {
		return sprintf(
			'%1$s (capped at %2$s): %3$s',
			baseFeeLabel,
			formatCurrency( fixed, fixedCurrency ),
			formatCurrency( -feeAmount, feeCurrency )
		);
	}

	return sprintf(
		'%1$s (%2$f%% + %3$s): %4$s',
		baseFeeLabel,
		formatFee( percentage ),
		formatCurrency( fixed, fixedCurrency ),
		formatCurrency( -feeAmount, feeCurrency )
	);
};

const composeFXString = ( event ) => {
	if ( ! isFXEvent( event ) ) {
		return;
	}
	const {
		transaction_details: {
			customer_currency: customerCurrency,
			customer_amount: customerAmount,
			store_currency: storeCurrency,
			store_amount: storeAmount,
		},
	} = event;
	return formatFX(
		{ currency: customerCurrency, amount: customerAmount },
		{
			currency: storeCurrency,
			amount: storeAmount,
		}
	);
};

/**
 * Returns an array containing fee breakdown.
 *
 * @param {Object} event Event object
 *
 * @return {Array} Array of formatted fee strings
 */
const feeBreakdown = ( event ) => {
	if ( ! event?.fee_rates?.history ) {
		return;
	}

	// hide breakdown when there's only a base fee
	if ( isBaseFeeOnly( event ) ) {
		return;
	}

	const {
		fee_rates: { history },
	} = event;

	const feeLabelMapping = ( fixedRate, isCapped ) => ( {
		base: ( () => {
			if ( isCapped ) {
				/* translators: %2$s is the capped fee */
				return __( 'Base fee: capped at %2$s', 'woocommerce-payments' );
			}

			if ( 0 !== fixedRate ) {
				/* translators: %1$s% is the fee percentage and %2$s is the fixed rate */
				return __( 'Base fee: %1$s%% + %2$s', 'woocommerce-payments' );
			}

			/* translators: %1$s% is the fee percentage */
			return __( 'Base fee: %1$s%%', 'woocommerce-payments' );
		} )(),

		'additional-international':
			0 !== fixedRate
				? __(
						/* translators: %1$s% is the fee percentage and %2$s is the fixed rate */
						'International card fee: %1$s%% + %2$s',
						'woocommerce-payments'
				  )
				: __(
						/* translators: %1$s% is the fee percentage */
						'International card fee: %1$s%%',
						'woocommerce-payments'
				  ),
		'additional-fx':
			0 !== fixedRate
				? __(
						/* translators: %1$s% is the fee percentage and %2$s is the fixed rate */
						'Foreign exchange fee: %1$s%% + %2$s',
						'woocommerce-payments'
				  )
				: __(
						/* translators: %1$s% is the fee percentage */
						'Foreign exchange fee: %1$s%%',
						'woocommerce-payments'
				  ),
		'additional-wcpay-subscription':
			0 !== fixedRate
				? __(
						/* translators: %1$s% is the fee amount and %2$s is the fixed rate */
						'Subscription transaction fee: %1$s%% + %2$s',
						'woocommerce-payments'
				  )
				: __(
						/* translators: %1$s% is the fee amount */
						'Subscription transaction fee: %1$s%%',
						'woocommerce-payments'
				  ),
		discount: __( 'Discount', 'woocommerce-payments' ),
	} );

	const renderDiscountSplit = (
		percentageRateFormatted,
		fixedRateFormatted
	) => {
		return (
			<ul className="discount-split-list">
				<li>
					{ __( 'Variable fee: ', 'woocommerce-payments' ) }
					{ percentageRateFormatted }%
				</li>
				<li>
					{ __( 'Fixed fee: ', 'woocommerce-payments' ) }
					{ fixedRateFormatted }
				</li>
			</ul>
		);
	};

	const feeHistoryList = history.map( ( fee ) => {
		let labelKey = fee.type;
		if ( fee.additional_type ) {
			labelKey += `-${ fee.additional_type }`;
		}

		const {
			percentage_rate: percentageRate,
			fixed_rate: fixedRate,
			currency,
			capped: isCapped,
		} = fee;

		const percentageRateFormatted = formatFee( percentageRate );
		const fixedRateFormatted = formatCurrency( fixedRate, currency );

		return (
			<li key={ labelKey }>
				{ sprintf(
					feeLabelMapping( fixedRate, isCapped )[ labelKey ],
					percentageRateFormatted,
					fixedRateFormatted
				) }

				{ 'discount' === fee.type &&
					renderDiscountSplit(
						percentageRateFormatted,
						fixedRateFormatted
					) }
			</li>
		);
	} );

	return <ul className="fee-breakdown-list"> { feeHistoryList } </ul>;
};

/**
 * Formats an event into one or more payment timeline items
 *
 * @param {Object} event An event data
 *
 * @return {Array} Payment timeline items
 */
const mapEventToTimelineItems = ( event ) => {
	const { type } = event;

	const stringWithAmount = ( headline, amount, explicit = false ) =>
		sprintf(
			headline,
			explicit
				? formatExplicitCurrency( amount, event.currency )
				: formatCurrency( amount, event.currency )
		);

	switch ( type ) {
		case 'authorized':
			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Authorized', 'woocommerce-payments' )
				),
				getMainTimelineItem(
					event,
					stringWithAmount(
						/* translators: %s is a monetary amount */
						__(
							'A payment of %s was successfully authorized.',
							'woocommerce-payments'
						),
						event.amount,
						true
					),
					'checkmark',
					'is-warning'
				),
			];
		case 'authorization_voided':
			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Authorization voided', 'woocommerce-payments' )
				),
				getMainTimelineItem(
					event,
					stringWithAmount(
						__(
							/* translators: %s is a monetary amount */
							'Authorization for %s was voided.',
							'woocommerce-payments'
						),
						event.amount,
						true
					),
					'checkmark',
					'is-warning'
				),
			];
		case 'authorization_expired':
			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Authorization expired', 'woocommerce-payments' )
				),
				getMainTimelineItem(
					event,
					stringWithAmount(
						__(
							/* translators: %s is a monetary amount */
							'Authorization for %s expired.',
							'woocommerce-payments'
						),
						event.amount,
						true
					),
					'cross',
					'is-error'
				),
			];
		case 'captured':
			const formattedNet = composeNetString( event );
			const feeString = composeFeeString( event );
			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Paid', 'woocommerce-payments' )
				),
				getDepositTimelineItem( event, formattedNet, true ),
				getMainTimelineItem(
					event,
					stringWithAmount(
						__(
							/* translators: %s is a monetary amount */
							'A payment of %s was successfully charged.',
							'woocommerce-payments'
						),
						event.amount,
						true
					),
					'checkmark',
					'is-success',
					[
						composeFXString( event ),
						feeString,
						feeBreakdown( event ),
						sprintf(
							/* translators: %s is a monetary amount */
							__( 'Net deposit: %s', 'woocommerce-payments' ),
							formattedNet
						),
					]
				),
			];
		case 'partial_refund':
		case 'full_refund':
			const formattedAmount = formatExplicitCurrency(
				event.amount_refunded,
				event.currency
			);
			const depositAmount = isFXEvent( event )
				? formatExplicitCurrency(
						event.transaction_details.store_amount,
						event.transaction_details.store_currency
				  )
				: formattedAmount;
			return [
				getStatusChangeTimelineItem(
					event,
					'full_refund' === type
						? __( 'Refunded', 'woocommerce-payments' )
						: __( 'Partial refund', 'woocommerce-payments' )
				),
				getDepositTimelineItem( event, depositAmount, false ),
				getMainTimelineItem(
					event,
					sprintf(
						__(
							/* translators: %s is a monetary amount */
							'A payment of %s was successfully refunded.',
							'woocommerce-payments'
						),
						formattedAmount
					),
					'checkmark',
					'is-success',
					[ composeFXString( event ) ]
				),
			];
		case 'failed':
			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Failed', 'woocommerce-payments' )
				),
				getMainTimelineItem(
					event,
					stringWithAmount(
						/* translators: %s is a monetary amount */
						__( 'A payment of %s failed.', 'woocommerce-payments' ),
						event.amount,
						true
					),
					'cross',
					'is-error'
				),
			];
		case 'dispute_needs_response':
			let reasonHeadline = __(
				'Payment disputed',
				'woocommerce-payments'
			);
			if ( disputeReasons[ event.reason ] ) {
				reasonHeadline = sprintf(
					/* translators: %s is a monetary amount */
					__( 'Payment disputed as %s.', 'woocommerce-payments' ),
					disputeReasons[ event.reason ].display
				);
			}

			const disputeUrl = getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/disputes/details',
				id: event.dispute_id,
			} );

			let depositTimelineItem;
			if ( null === event.amount ) {
				depositTimelineItem = {
					date: new Date( event.datetime * 1000 ),
					icon: getIcon( 'info-outline' ),
					headline: __(
						'No funds have been withdrawn yet.',
						'woocommerce-payments'
					),
					body: [
						__(
							// eslint-disable-next-line max-len
							"The cardholder's bank is requesting more information to decide whether to return these funds to the cardholder.",
							'woocommerce-payments'
						),
					],
				};
			} else {
				const formattedExplicitTotal = formatExplicitCurrency(
					Math.abs( event.amount ) + Math.abs( event.fee ),
					event.currency
				);
				const disputedAmount = isFXEvent( event )
					? formatCurrency(
							event.transaction_details.customer_amount,
							event.transaction_details.customer_currency
					  )
					: formatCurrency( event.amount, event.currency );
				depositTimelineItem = getDepositTimelineItem(
					event,
					formattedExplicitTotal,
					false,
					[
						sprintf(
							/* translators: %s is a monetary amount */
							__( 'Disputed amount: %s', 'woocommerce-payments' ),
							disputedAmount
						),
						composeFXString( event ),
						sprintf(
							/* translators: %s is a monetary amount */
							__( 'Fee: %s', 'woocommerce-payments' ),
							formatCurrency( event.fee, event.currency )
						),
					]
				);
			}

			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Disputed: Needs response', 'woocommerce-payments' )
				),
				depositTimelineItem,
				getMainTimelineItem(
					event,
					reasonHeadline,
					'cross',
					'is-error',
					[
						// eslint-disable-next-line react/jsx-key
						<Link href={ disputeUrl }>
							{ __( 'View dispute', 'woocommerce-payments' ) }
						</Link>,
					]
				),
			];
		case 'dispute_in_review':
			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Disputed: In review', 'woocommerce-payments' )
				),
				getMainTimelineItem(
					event,
					__(
						'Challenge evidence submitted.',
						'woocommerce-payments'
					),
					'checkmark',
					'is-success'
				),
			];
		case 'dispute_won':
			const formattedExplicitTotal = formatExplicitCurrency(
				Math.abs( event.amount ) + Math.abs( event.fee ),
				event.currency
			);
			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Disputed: Won', 'woocommerce-payments' )
				),
				getDepositTimelineItem( event, formattedExplicitTotal, true, [
					sprintf(
						/* translators: %s is a monetary amount */
						__( 'Dispute reversal: %s', 'woocommerce-payments' ),
						formatCurrency( event.amount, event.currency )
					),
					sprintf(
						/* translators: %s is a monetary amount */
						__( 'Fee refund: %s', 'woocommerce-payments' ),
						formatCurrency( Math.abs( event.fee ), event.currency )
					),
				] ),
				getMainTimelineItem(
					event,
					__(
						'Dispute won! The bank ruled in your favor.',
						'woocommerce-payments'
					),
					'notice-outline',
					'is-success'
				),
			];
		case 'dispute_lost':
			return [
				getStatusChangeTimelineItem(
					event,
					__( 'Disputed: Lost', 'woocommerce-payments' )
				),
				getMainTimelineItem(
					event,
					__(
						'Dispute lost. The bank ruled in favor of your customer.',
						'woocommerce-payments'
					),
					'cross',
					'is-error'
				),
			];
		case 'dispute_warning_closed':
			return [
				getMainTimelineItem(
					event,
					__(
						'Dispute inquiry closed. The bank chose not to pursue this dispute.',
						'woocommerce-payments'
					),
					'notice-outline',
					'is-success'
				),
			];
		case 'dispute_charge_refunded':
			return [
				getMainTimelineItem(
					event,
					__(
						'The disputed charge has been refunded.',
						'woocommerce-payments'
					),
					'notice-outline',
					'is-success'
				),
			];
		default:
			return [];
	}
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
