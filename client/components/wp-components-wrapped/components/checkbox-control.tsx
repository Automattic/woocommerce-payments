/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { CheckboxControl as BundledCheckboxControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const CheckboxControl = makeWrappedComponent(
	BundledCheckboxControl,
	'CheckboxControl'
);
