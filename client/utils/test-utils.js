/** @format */

/**
 * External dependencies
 */
import moment from 'moment';

export function getUnformattedAmount( formattedAmount ) {
	let amount = formattedAmount.replace( /[^0-9,.' ]/g, '' ).trim();
	amount = amount.replace( ',', '.' ); // Euro fix
	return amount;
}

export function getUserTimeZone() {
	return moment( new Date() ).format( 'Z' );
}
