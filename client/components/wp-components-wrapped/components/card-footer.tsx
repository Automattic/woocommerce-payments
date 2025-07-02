/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { CardFooter as BundledCardFooter } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const CardFooter = makeWrappedComponent(
	BundledCardFooter,
	'CardFooter'
);
