/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { timeOfDayStrings } from './strings';

/**
 * Calculates the time of day based on the browser's time.
 *
 * @param {Date} date A date object to calculate the time of day for. Defaults to the current time.
 * @return {string} The time of day (translatable string). One of 'morning', 'afternoon' or 'evening'.
 *
 */
export const getTimeOfDayString = ( date: Date = new Date() ): string => {
	const hour = date.getHours();
	if ( hour >= 18 ) {
		return timeOfDayStrings.evening;
	}
	if ( hour >= 12 ) {
		return timeOfDayStrings.afternoon;
	}
	return timeOfDayStrings.morning;
};

/**
 * Returns a greeting string based on the time of day and a given name, if provided.
 *
 * @param {string} name An name to include in the greeting, optional.
 * @param {Date} date A date object to calculate the time of day for. Defaults to the current time.
 * @return {string} A greeting string.
 */
export const getGreeting = (
	name?: string,
	date: Date = new Date()
): string => {
	const timeOfDay = getTimeOfDayString( date );
	const adjective = __( `Good `, 'woocommerce-payments' );
	let greeting = `${ adjective }${ timeOfDay }`;
	if ( name ) {
		greeting += `, ${ name }!`;
	} else {
		greeting += '!';
	}
	return greeting;
};
