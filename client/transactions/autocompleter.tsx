/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import computeSuggestionMatch from 'utils/compute-suggestion-match';

/**
 * @typedef {Object} Completer
 */

// add any other params here
type completionOption = { label: string };

/**
 * A transaction completer.
 * See https://github.com/WordPress/gutenberg/tree/master/packages/components/src/autocomplete#the-completer-interface
 *
 * @type {Completer}
 */
export default {
	name: 'transactions',
	className: 'woocommerce-search__transactions-result',
	options( term: string ) {
		const query = term ? { search_term: term } : {};
		return apiFetch( {
			path: addQueryArgs( '/wc/v3/payments/transactions/search', query ),
		} );
	},
	isDebounced: true,
	getOptionIdentifier( option: completionOption ): string {
		return option.label;
	},
	getOptionKeywords( option: completionOption ): [ string ] {
		return [ option.label ];
	},
	getFreeTextOptions( query ) {
		const label = (
			<span key="name" className="woocommerce-search__result-name">
				{ interpolateComponents( {
					mixedString: __(
						'All transactions with customer names or billing emails that include {{query /}}',
						'woocommerce-payments'
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
			key: 'all',
			label,
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
