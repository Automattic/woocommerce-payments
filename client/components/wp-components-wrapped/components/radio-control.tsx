/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { RadioControl as BundledRadioControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const RadioControl = makeWrappedComponent(
	BundledRadioControl,
	'RadioControl'
);
