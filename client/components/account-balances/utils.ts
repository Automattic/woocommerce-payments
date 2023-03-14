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
	// Morning 5am -11.59am
	if ( hour >= 5 && hour < 12 ) {
		return timeOfDayStrings.morning;
	}
	// Afternoon 12pm – 4:59pm
	if ( hour >= 12 && hour < 17 ) {
		return timeOfDayStrings.afternoon;
	}
	// Evening 5pm – 4:59am
	return timeOfDayStrings.evening;
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
