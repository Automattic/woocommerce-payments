/**
 * External dependencies
 */
import * as React from 'react';
import { addQueryArgs } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import computeSuggestionMatch from 'utils/compute-suggestion-match';

/**
 * @typedef {Object} Completer
 */

interface CompletionOption {
	key: string;
	label: string;
}

const setupAutocompleter = ( status: string ): unknown => ( {
	name: 'transactions',
	className: 'woocommerce-search__transactions-result',
	async options( term: string ): Promise< CompletionOption[] > {
		const query = term ? { search_term: term } : {};
		const options: CompletionOption[] = await apiFetch( {
			path: addQueryArgs(
				'/wc/v3/payments/transactions/fraud-outcomes/search',
				{
					status,
					...query,
				}
			),
		} );

		if ( ! term ) {
			return options;
		}

		return options.filter(
			( { label } ) =>
				label
					.toLocaleLowerCase()
					.indexOf( term.toLocaleLowerCase() ) !== -1
		);
	},
	isDebounced: true,
	getOptionIdentifier( option: CompletionOption ): string {
		return option.label;
	},
	getOptionKeywords( option: CompletionOption ): string[] {
		return [ option.label ];
	},
	getOptionLabel( option: CompletionOption, query: string ): JSX.Element {
		const match = computeSuggestionMatch( option.label, query );

		return (
			<span
				key="name"
				className="woocommerce-search__result-name"
				aria-label={ option.label }
			>
				{ match?.suggestionBeforeMatch }
				<strong className="components-form-token-field__suggestion-match">
					{ match?.suggestionMatch }
				</strong>
				{ match?.suggestionAfterMatch }
			</span>
		);
	},
	// This is slightly different than gutenberg/Autocomplete, we don't support different methods
	// of replace/insertion, so we can just return the value.
	getOptionCompletion( option: CompletionOption ): CompletionOption {
		return { key: option.label, label: option.label };
	},
} );

export default setupAutocompleter;
