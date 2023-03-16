/**
 * External dependencies
 */
import { sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { greetingStrings } from './strings';

export type TimeOfDay = 'morning' | 'afternoon' | 'evening';

/**
 * Calculates the time of day based on the browser's time.
 *
 * @param {Date} date A date object to calculate the time of day for. Defaults to the current time.
 * @return {TimeOfDay} The time of day. One of 'morning', 'afternoon' or 'evening'.
 */
export const getTimeOfDayString = ( date: Date = new Date() ): TimeOfDay => {
	const hour = date.getHours();
	// Morning 5am -11.59am
	if ( hour >= 5 && hour < 12 ) {
		return 'morning';
	}
	// Afternoon 12pm – 4:59pm
	if ( hour >= 12 && hour < 17 ) {
		return 'afternoon';
	}
	// Evening 5pm – 4:59am
	return 'evening';
};

/**
 * Returns a greeting string based on the time of day and a given name, if provided.
 *
 * @param {string} name A name to include in the greeting, optional.
 * @param {Date} date A date object to calculate the time of day for. Defaults to the current time.
 * @return {string} A greeting string.
 */
export const getGreeting = (
	name?: string,
	date: Date = new Date()
): string => {
	const timeOfDay = getTimeOfDayString( date );
	let greeting = greetingStrings.withoutName[ timeOfDay ];
	if ( name ) {
		greeting = sprintf( greetingStrings.withName[ timeOfDay ], name );
	}
	greeting += ' 👋';
	return greeting;
};
