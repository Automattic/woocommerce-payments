/** @format */

/**
 * External dependencies
 */
import moment from 'moment';

export function getUserTimeZone() {
	return moment( new Date() ).format( 'Z' );
}
