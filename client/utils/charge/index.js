/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */

const failedOutcomeTypes = [ 'issuer_declined', 'invalid' ];
const blockedOutcomeTypes = [ 'blocked' ];
const disputeFee = 1500;

export const getDisputeStatus = ( dispute = {} ) => dispute.status || null;

export const getChargeOutcomeType = ( charge = {} ) => charge.outcome ? charge.outcome.type : null;

export const isChargeSuccessful = ( charge = {} ) =>
	charge.status === 'succeeded' && charge.paid === true;

export const isChargeFailed = ( charge = {} ) =>
	charge.status === 'failed' && failedOutcomeTypes.includes( getChargeOutcomeType( charge ) );

export const isChargeBlocked = ( charge = {} ) =>
	charge.status === 'failed' && blockedOutcomeTypes.includes( getChargeOutcomeType( charge ) );

export const isChargeCaptured = ( charge = {} ) => charge.captured === true;

export const isChargeDisputed = ( charge = {} ) => charge.disputed === true;

export const isChargeRefunded = ( charge = {} ) => charge.amount_refunded > 0;

export const isChargeFullyRefunded = ( charge = {} ) => charge.refunded === true;

export const isChargePartiallyRefunded = ( charge = {} ) =>
	isChargeRefunded( charge ) && ! isChargeFullyRefunded( charge );

export const mapDisputeStatusToChargeStatus = ( status ) => {
	switch ( status ) {
		case 'warning_needs_response':
		case 'needs_response':
			return 'disputed_needs_response';
		case 'warning_under_review':
		case 'under_review':
			return 'disputed_under_review';
		case 'won':
			return 'disputed_won';
		case 'lost':
			return 'disputed_lost';
		default:
			return 'disputed';
	}
};

/* TODO: implement authorization and SCA charge statuses */
export const getChargeStatus = ( charge = {} ) => {
	if ( isChargeFailed( charge ) ) {
		return 'failed';
	}
	if ( isChargeBlocked( charge ) ) {
		return 'blocked';
	}
	if ( isChargeDisputed( charge ) ) {
		return mapDisputeStatusToChargeStatus( getDisputeStatus( charge.dispute ) );
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
	return '';
};

/**
 * Calculates display values for charge amounts.
 *
 * @param {object} charge The full charge object.
 * @return {object} An object, containing the `net`, `fee`, and `refund` amounts in Stripe format (*100).
 */
export const getChargeAmounts = ( charge ) => {
	let refunded, fee;

	// The base fee is the application fee.
	fee = charge.application_fee_amount;
	refunded = 0;

	if ( isChargeDisputed( charge ) && 'won' !== charge.dispute.status ) {
		fee += disputeFee;
		refunded = charge.dispute.amount;
	} else if ( isChargeRefunded( charge ) ) {
		refunded = charge.amount_refunded;
	}

	// The final net amount equals the original amount, decreased by the fee(s) and refunded amount.
	const net = charge.amount - fee - ( refunded || 0 );

	return { net, fee, refunded };
};
