/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { DropdownMenu as BundledDropdownMenu } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const DropdownMenu = makeWrappedComponent(
	BundledDropdownMenu,
	'DropdownMenu'
);
