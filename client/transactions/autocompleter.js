/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';
import interpolateComponents from 'interpolate-components';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */

/**
 * Parse a string suggestion, split apart by where the first matching query is.
 * Used to display matched partial in bold.
 *
 * @param {string} suggestion The item's label as returned from the API.
 * @param {string} query The search term to match in the string.
 * @return {Object} A list in three parts: before, match, and after.
 */
export function computeSuggestionMatch( suggestion, query ) {
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
		suggestionBeforeMatch: decodeEntities( suggestion.substring( 0, indexOfMatch ) ),
		suggestionMatch: decodeEntities( suggestion.substring(
			indexOfMatch,
			indexOfMatch + query.length
		) ),
		suggestionAfterMatch: decodeEntities( suggestion.substring(
			indexOfMatch + query.length
		) ),
	};
}

/**
 * @typedef {Object} Completer
 */

/**
 * A transaction completer.
 * See https://github.com/WordPress/gutenberg/tree/master/packages/components/src/autocomplete#the-completer-interface
 *
 * @type {Completer}
 */
export default {
	name: 'transactions',
	className: 'woocommerce-search__transactions-result',
	options( name ) {
		const query = name ? { query: name } : {};
		return apiFetch( {
			path: addQueryArgs( '/wc/v3/payments/transactions/search', query ),
		} );
	},
	isDebounced: true,
	getOptionIdentifier( option ) {
		return option.label;
	},
	getOptionKeywords( option ) {
		return [ option.label ];
	},
	getFreeTextOptions( query ) {
		const label = (
			<span key="name" className="woocommerce-search__result-name">
				{ interpolateComponents( {
					mixedString: __(
						'All transactions with transaction names or emails that include {{query /}}',
						'woocommerce-admin'
					),
					components: {
						query: (
							<strong className="components-form-token-field__suggestion-match">
								{ query }
							</strong>
						),
					},
				} ) }
			</span>
		);
		const nameOption = {
			key: 'name',
			label,
			// eslint-disable-next-line camelcase
			value: { label: query },
		};

		return [ nameOption ];
	},
	getOptionLabel( option, query ) {
		const match = computeSuggestionMatch( option.label, query );
		return (
			<span
				key="name"
				className="woocommerce-search__result-name"
				aria-label={ option.label }
			>
				{ match.suggestionBeforeMatch }
				<strong className="components-form-token-field__suggestion-match">
					{ match.suggestionMatch }
				</strong>
				{ match.suggestionAfterMatch }
			</span>
		);
	},
	// This is slightly different than gutenberg/Autocomplete, we don't support different methods
	// of replace/insertion, so we can just return the value.
	getOptionCompletion( option ) {
		return {
			key: option.label,
			label: option.label,
		};
	},
};
