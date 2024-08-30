/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 *	Joins a list using commas plus `and` or the translated word.
 *  Example: [a, b, c] => `a, b, and c`.
 *  Example: [a, b] => `a and b`.
 *
 * @param {string[]} items of strings to join
 * @return {string} joined list with a conjunction
 */
export const formatListOfItems = ( items: string[] ): string => {
	if ( items.length === 1 ) {
		return items[ 0 ];
	}

	if ( items.length === 2 ) {
		return sprintf(
			// eslint-disable-next-line max-len
			// translators: the first %s is a placeholder for the first item in a list of two. The last %s corresponds to the second item in the list.
			__( '%s and %s', 'woocommerce-payments' ),
			items[ 0 ],
			items[ 1 ]
		);
	}

	const lastItem = items.pop();
	return sprintf(
		// it'll be up to translators to decide whether the last comma is necessary in the language they're translating.
		// eslint-disable-next-line max-len
		// translators: the first %s is a placeholder for a comma-separated list of one or more items. The last %s corresponds to the last item in the list.
		__( '%s, and %s', 'woocommerce-payments' ),
		items.join( ', ' ),
		lastItem
	);
};
