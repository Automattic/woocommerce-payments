/** @format **/

/**
 * External dependencies
 */
import { get } from 'lodash';

/**
 * Internal dependencies.
 */

export const isTransactionRefunded = ( transaction ) => get( transaction, 'source.refunded' );

export const isTransactionFullyRefunded = ( transaction ) => get( transaction, 'source.amount_refunded' ) === transaction.amount;

export const isTransactionPartiallyRefunded = ( transaction ) =>
	isTransactionRefunded( transaction ) && ! isTransactionFullyRefunded( transaction );
