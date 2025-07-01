/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { SnackbarList as BundledSnackbarList } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const SnackbarList = makeWrappedComponent(
	BundledSnackbarList,
	'SnackbarList'
);
