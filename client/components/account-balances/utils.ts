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
