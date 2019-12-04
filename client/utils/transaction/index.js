/** @format **/

/**
 * External dependencies
 */
import { get } from 'lodash';

/**
 * Internal dependencies.
 */

export const getCharge = ( transaction ) => get( transaction, 'type' ) === 'refund'
	? get( transaction, 'source.charge' ) || {}
	: get( transaction, 'source' ) || {};

export const isTransactionSuccessful = ( transaction ) =>
	getCharge( transaction ).status === 'succeeded' && getCharge( transaction ).paid === true;

export const isTransactionFailed = ( transaction ) => getCharge( transaction ).status === 'failed';

export const isTransactionCaptured = ( transaction ) => getCharge( transaction ).captured === true;

export const isTransactionDisputed = ( transaction ) => getCharge( transaction ).disputed === true;

export const isTransactionRefunded = ( transaction ) => getCharge( transaction ).amount_refunded > 0;

export const isTransactionFullyRefunded = ( transaction ) => getCharge( transaction ).refunded === true;

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
