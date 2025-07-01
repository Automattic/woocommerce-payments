/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { ToggleControl as BundledToggleControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const ToggleControl = makeWrappedComponent(
	BundledToggleControl,
	'ToggleControl'
);
