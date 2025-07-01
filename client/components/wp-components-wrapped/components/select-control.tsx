/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { SelectControl as BundledSelectControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const SelectControl = makeWrappedComponent(
	BundledSelectControl,
	'SelectControl'
);
