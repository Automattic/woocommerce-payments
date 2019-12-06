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

/* TODO: implement other charge statuses */
export const getChargeStatus = ( charge = {} ) => {
	if ( isChargeFailed( charge ) ) {
		return 'failed';
	}
	if ( isChargeDisputed( charge ) ) {
		return 'disputed';
	}
	if ( isChargePartiallyRefunded( charge ) ) {
		return 'partially-refunded';
	}
	if ( isChargeFullyRefunded( charge ) ) {
		return 'fully-refunded';
	}
	if ( isChargeSuccessful( charge ) && isChargeCaptured( charge ) ) {
		return 'paid';
	}
	if ( isChargeSuccessful( charge ) && ! isChargeCaptured( charge ) ) {
		return 'authorized';
	}
	return '';
};
