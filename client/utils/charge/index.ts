/** @format **/

/**
 * External dependencies
 */
import { sumBy, get } from 'lodash';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Dispute } from 'types/disputes';
import { Charge, ChargeAmounts } from 'types/charges';

const failedOutcomeTypes = [ 'issuer_declined', 'invalid' ];
const blockedOutcomeTypes = [ 'blocked' ];

export const getDisputeStatus = ( dispute: Dispute = <Dispute>{} ): string =>
	dispute.status || '';

export const getChargeOutcomeType = ( charge: Charge = <Charge>{} ): string =>
	charge.outcome ? charge.outcome.type : '';

export const isChargeSuccessful = ( charge: Charge = <Charge>{} ): boolean =>
	'succeeded' === charge.status && true === charge.paid;

export const isChargeFailed = ( charge: Charge = <Charge>{} ): boolean =>
	'failed' === charge.status &&
	failedOutcomeTypes.includes( getChargeOutcomeType( charge ) );

export const isChargeBlocked = ( charge: Charge = <Charge>{} ): boolean =>
	'failed' === charge.status &&
	blockedOutcomeTypes.includes( getChargeOutcomeType( charge ) );

export const isChargeCaptured = ( charge: Charge = <Charge>{} ): boolean =>
	true === charge.captured;

export const isChargeDisputed = ( charge: Charge = <Charge>{} ): boolean =>
	true === charge.disputed;

export const isChargeRefunded = ( charge: Charge = <Charge>{} ): boolean =>
	0 < charge.amount_refunded;

export const isChargeRefundFailed = ( charge: Charge = <Charge>{} ): boolean =>
	false === charge.refunded && get( charge, 'refunds.data', [] ).length > 0;

export const isChargeFullyRefunded = ( charge: Charge = <Charge>{} ): boolean =>
	true === charge.refunded;

export const isChargePartiallyRefunded = (
	charge: Charge = <Charge>{}
): boolean => isChargeRefunded( charge ) && ! isChargeFullyRefunded( charge );

/* TODO: implement authorization and SCA charge statuses */
export const getChargeStatus = ( charge: Charge = <Charge>{} ): string => {
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
	if ( isChargeRefundFailed( charge ) ) {
		return 'refund_failed';
	}
	if ( isChargeSuccessful( charge ) ) {
		return isChargeCaptured( charge ) ? 'paid' : 'authorized';
	}
	return charge.status;
};

/**
 * Calculates display values for charge amounts in settlement currency.
 *
 * @param {Charge} charge The full charge object.
 * @return {ChargeAmounts} An object, containing the `currency`, `amount`, `net`, `fee`, and `refunded` amounts in Stripe format (*100).
 */
export const getChargeAmounts = ( charge: Charge ): ChargeAmounts => {
	const balance = charge.balance_transaction
		? {
				currency: charge.balance_transaction.currency,
				amount: charge.balance_transaction.amount,
				fee: charge.balance_transaction.fee,
				refunded: 0,
				net: 0,
		  }
		: {
				currency: charge.currency,
				amount: charge.amount,
				fee: charge.application_fee_amount,
				refunded: 0,
				net: 0,
		  };

	if ( isChargeRefunded( charge ) ) {
		// Refund balance_transactions have negative amount.
		balance.refunded -= sumBy(
			charge.refunds.data,
			'balance_transaction.amount'
		);
	}

	if ( isChargeDisputed( charge ) && typeof charge.dispute !== 'undefined' ) {
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

/**
 * Displays the transaction's channel: Online | In-Person.
 *
 * This method is called in two places: The individual transaction page, and the list of transactions page.
 * In the individual transaction page, we are getting the data from Stripe, so we pass the transaction.type
 * which can be card_present or interac_present for In-Person payments.
 * In the list of transactions, the type holds the brand of the payment method, so we aren't passing it.
 * Instead, we pass the transaction.channel directly, which might be in_person|online.
 *
 * @param {string} type The transaction type.
 * @return {string} Online or In-Person.
 *
 */
export const getChargeChannel = ( type: string ): string => {
	if (
		type === 'card_present' ||
		type === 'interac_present' ||
		type === 'in_person'
	) {
		return __( 'In-Person', 'woocommerce-payments' );
	}

	return __( 'Online', 'woocommerce-payments' );
};
