/** @format **/

/**
 * External dependencies
 */
import { get } from 'lodash';

/**
 * Internal dependencies.
 */

export const getTransactionSourceCharge = ( transaction ) => get( transaction, 'type' ) === 'refund'
	? get( transaction, 'source.charge' ) || {}
	: get( transaction, 'source' ) || {};

export const isTransactionSuccessful = ( transaction ) =>
	getTransactionSourceCharge( transaction ).status === 'succeeded' && getTransactionSourceCharge( transaction ).paid === true;

export const isTransactionFailed = ( transaction ) => getTransactionSourceCharge( transaction ).status === 'failed';

export const isTransactionCaptured = ( transaction ) => getTransactionSourceCharge( transaction ).captured === true;

export const isTransactionDisputed = ( transaction ) => getTransactionSourceCharge( transaction ).disputed === true;

export const isTransactionRefunded = ( transaction ) => getTransactionSourceCharge( transaction ).amount_refunded > 0;

export const isTransactionFullyRefunded = ( transaction ) => getTransactionSourceCharge( transaction ).refunded === true;

export const isTransactionPartiallyRefunded = ( transaction ) =>
	isTransactionRefunded( transaction ) && ! isTransactionFullyRefunded( transaction );

/* TODO: implement other transaction statuses */
export const getTransactionStatus = ( transaction ) => {
	if ( isTransactionFailed( transaction ) ) {
		return 'failed';
	}
	if ( isTransactionDisputed( transaction ) ) {
		return 'disputed';
	}
	if ( isTransactionPartiallyRefunded( transaction ) ) {
		return 'partially-refunded';
	}
	if ( isTransactionFullyRefunded( transaction ) ) {
		return 'fully-refunded';
	}
	if ( isTransactionSuccessful( transaction ) && isTransactionCaptured( transaction ) ) {
		return 'paid';
	}
	if ( isTransactionSuccessful( transaction ) && ! isTransactionCaptured( transaction ) ) {
		return 'authorized';
	}
	return '';
};
