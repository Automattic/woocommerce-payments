/** @format **/

/**
 * External dependencies
 */
import { sumBy, isObject } from 'lodash';

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
 * Calculates display values for charge amounts in settlement currency.
 *
 * @param {Object} charge The full charge object.
 * @return {Object} An object, containing the `currency`, `amount`, `net`, `fee`, and `refunded` amounts in Stripe format (*100).
 */
export const getChargeAmounts = ( charge ) => {
	const balance = {
		currency: charge.currency || 'USD',
		amount: charge.amount,
		fee: charge.application_fee_amount,
		refunded: 0,
		net: 0,
	};

	if ( isChargeRefunded( charge ) ) {
		balance.refunded += charge.amount_refunded;
	}

	if (
		isObject( charge.balance_transaction ) &&
		charge.balance_transaction.currency !== charge.currency
	) {
		balance.currency = charge.balance_transaction.currency;
		balance.amount = charge.balance_transaction.amount;
		balance.fee = charge.balance_transaction.fee;
		balance.refunded = 0;

		if ( isChargeRefunded( charge ) ) {
			// Refund balance_transactions have negative amount.
			balance.refunded -= sumBy(
				charge.refunds.data,
				'balance_transaction.amount'
			);
		}
	}

	// Dispute balance transactions are always in settlement currency.
	if ( isChargeDisputed( charge ) ) {
		balance.fee += sumBy( charge.dispute.balance_transactions, 'fee' );
		balance.refunded -= sumBy(
			charge.dispute.balance_transactions,
			'amount'
		);
	}

	// The final net amount equals the original amount, decreased by the fee(s) and refunded amount.
	balance.net = balance.amount - balance.fee - balance.refunded;

	return balance;
};
