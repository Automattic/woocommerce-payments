/**
 * External dependencies
 */
// @ts-expect-error: Suppressing Module '"@wordpress/components"' has no exported member 'SearchControl'.
// eslint-disable-next-line no-restricted-syntax
import { SearchControl as BundledSearchControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const SearchControl = makeWrappedComponent(
	BundledSearchControl,
	'SearchControl'
);
