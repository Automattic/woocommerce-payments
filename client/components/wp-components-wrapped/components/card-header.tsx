/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { CardHeader as BundledCardHeader } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const CardHeader = makeWrappedComponent(
	BundledCardHeader,
	'CardHeader'
);
