/** @format **/

/**
 * External dependencies
 */
/**
 * Internal dependencies.
 */

export const isChargeSuccessful = ( charge = {} ) =>
	charge.status === 'succeeded' && charge.paid === true;

export const isChargeFailed = ( charge = {} ) => charge.status === 'failed';

export const isChargeCaptured = ( charge = {} ) => charge.captured === true;

export const isChargeDisputed = ( charge = {} ) => charge.disputed === true;

export const isChargeRefunded = ( charge = {} ) => charge.amount_refunded > 0;

export const isChargeFullyRefunded = ( charge = {} ) => charge.refunded === true;

export const isChargePartiallyRefunded = ( charge = {} ) =>
	isChargeRefunded( charge ) && ! isChargeFullyRefunded( charge );

export const getChargeDisputeStatus = ( charge = {} ) => charge.dispute ? charge.dispute.status : null;

/* TODO: implement other charge statuses */
export const getChargeStatus = ( charge = {} ) => {
	if ( isChargeFailed( charge ) ) {
		return 'failed';
	}
	if ( isChargeDisputed( charge ) ) {
		switch ( getChargeDisputeStatus( charge ) ) {
			case 'warning_needs_response':
			case 'needs_response':
				return 'disputed-needs-response';
			case 'warning_under_review':
			case 'under_review':
				return 'disputed-under-review';
			case 'won':
				return 'disputed-won';
			case 'lost':
				return 'disputed-lost';
		}
	}
	if ( isChargePartiallyRefunded( charge ) ) {
		return 'partially-refunded';
	}
	if ( isChargeFullyRefunded( charge ) ) {
		return 'fully-refunded';
	}
	if ( isChargeSuccessful( charge ) ) {
		return isChargeCaptured( charge ) ? 'paid' : 'authorized';
	}
	return '';
};
