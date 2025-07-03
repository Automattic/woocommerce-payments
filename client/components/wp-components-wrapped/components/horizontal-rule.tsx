/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { HorizontalRule as BundledHorizontalRule } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const HorizontalRule = makeWrappedComponent(
	// @ts-expect-error: suppressing because of how the HorizontalRule component is defined, but it's no problem.
	BundledHorizontalRule,
	'HorizontalRule'
);
