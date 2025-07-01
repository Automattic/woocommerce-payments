/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { TextareaControl as BundledTextareaControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const TextareaControl = makeWrappedComponent(
	BundledTextareaControl,
	'TextareaControl'
);
