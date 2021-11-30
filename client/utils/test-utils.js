/** @format */

/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

export function getUnformattedAmount( formattedAmount ) {
	let amount = formattedAmount.replace( /[^0-9,.' ]/g, '' ).trim();
	amount = amount.replace( ',', '.' ); // Euro fix
	return amount;
}

export function formatDate( date, format ) {
	return dateI18n( format, moment.utc( date ).toISOString(), true );
}
