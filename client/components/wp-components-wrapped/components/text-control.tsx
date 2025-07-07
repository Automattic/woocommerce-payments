/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { TextControl as BundledTextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const TextControl = makeWrappedComponent(
	BundledTextControl,
	'TextControl'
);
