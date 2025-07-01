/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { RangeControl as BundledRangeControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const RangeControl = makeWrappedComponent(
	BundledRangeControl,
	'RangeControl'
);
