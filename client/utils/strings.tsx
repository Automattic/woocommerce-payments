/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 *	Joins a list using commas plus `and` or the translated word.
 *  Example: [a, b, c] => `a, b and c`.
 *
 * @param {string[]} list of strings to join
 * @return {string} joined list with a conjunction
 */
export const joinWithConjunction = ( list: string[] ): string => {
	const commaSeparated = list.join( ', ' );
	return commaSeparated.replace(
		/\,(?=[^,]*$)/,
		__( ' and', 'woocommerce-payments' )
	);
};
