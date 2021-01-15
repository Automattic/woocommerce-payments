/** @format **/

/**
 * External dependencies
 */
import { sumBy } from 'lodash';

const failedOutcomeTypes = [ 'issuer_declined', 'invalid' ];
const blockedOutcomeTypes = [ 'blocked' ];

export const getDisputeStatus = ( dispute = {} ) => dispute.status || null;

export const getChargeOutcomeType = ( charge = {} ) =>
	charge.outcome ? charge.outcome.type : null;

export const isChargeSuccessful = ( charge = {} ) =>
	'succeeded' === charge.status && true === charge.paid;

export const isChargeFailed = ( charge = {} ) =>
	'failed' === charge.status &&
	failedOutcomeTypes.includes( getChargeOutcomeType( charge ) );

export const isChargeBlocked = ( charge = {} ) =>
	'failed' === charge.status &&
	blockedOutcomeTypes.includes( getChargeOutcomeType( charge ) );

export const isChargeCaptured = ( charge = {} ) => true === charge.captured;

export const isChargeDisputed = ( charge = {} ) => true === charge.disputed;

export const isChargeRefunded = ( charge = {} ) => 0 < charge.amount_refunded;

export const isChargeFullyRefunded = ( charge = {} ) =>
	true === charge.refunded;

export const isChargePartiallyRefunded = ( charge = {} ) =>
	isChargeRefunded( charge ) && ! isChargeFullyRefunded( charge );

/* TODO: implement authorization and SCA charge statuses */
export const getChargeStatus = ( charge = {} ) => {
	if ( isChargeFailed( charge ) ) {
		return 'failed';
	}
	if ( isChargeBlocked( charge ) ) {
		return 'blocked';
	}
	if ( isChargeDisputed( charge ) ) {
		return 'disputed_' + getDisputeStatus( charge.dispute );
	}
	if ( isChargePartiallyRefunded( charge ) ) {
		return 'refunded_partial';
	}
	if ( isChargeFullyRefunded( charge ) ) {
		return 'refunded_full';
	}
	if ( isChargeSuccessful( charge ) ) {
		return isChargeCaptured( charge ) ? 'paid' : 'authorized';
	}
	return charge.status;
};

/**
 * Calculates display values for charge amounts.
 *
 * @param {Object} charge The full charge object.
 * @return {Object} An object, containing the `net`, `fee`, and `refund` amounts in Stripe format (*100).
 */
export const getChargeAmounts = ( charge ) => {
	// The base fee is the application fee.
	let fee = charge.application_fee_amount;
	let refunded = 0;

	if ( isChargeDisputed( charge ) ) {
		fee += sumBy( charge.dispute.balance_transactions, 'fee' );
		refunded -= sumBy( charge.dispute.balance_transactions, 'amount' );
	}

	if ( isChargeRefunded( charge ) ) {
		refunded += charge.amount_refunded;
	}

	// The final net amount equals the original amount, decreased by the fee(s) and refunded amount.
	const net = charge.amount - fee - refunded;

	return { net, fee, refunded };
};
