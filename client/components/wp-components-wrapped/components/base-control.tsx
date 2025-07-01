/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { BaseControl as BundledBaseControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const BaseControl = makeWrappedComponent(
	BundledBaseControl,
	'BaseControl'
);
