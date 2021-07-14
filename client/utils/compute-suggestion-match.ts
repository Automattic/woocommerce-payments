/**
 * External dependencies
 */
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */

interface SuggestionMatch {
	suggestionBeforeMatch: string;
	suggestionMatch: string;
	suggestionAfterMatch: string;
}

/**
 * Parse a string suggestion, split apart by where the first matching query is.
 * Used to display matched partial in bold.
 *
 * @param {string} suggestion The item's label as returned from the API.
 * @param {string} query The search term to match in the string.
 * @return {Object} A list in three parts: before, match, and after.
 */
export default function computeSuggestionMatch(
	suggestion: string,
	query: string
): SuggestionMatch | null {
	if ( ! query ) {
		return null;
	}
	const indexOfMatch = suggestion
		.toLocaleLowerCase()
		.indexOf( query.toLocaleLowerCase() );

	if ( -1 === indexOfMatch ) {
		return null;
	}

	return {
		suggestionBeforeMatch: decodeEntities(
			suggestion.substring( 0, indexOfMatch )
		),
		suggestionMatch: decodeEntities(
			suggestion.substring( indexOfMatch, indexOfMatch + query.length )
		),
		suggestionAfterMatch: decodeEntities(
			suggestion.substring( indexOfMatch + query.length )
		),
	};
}
