/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { CardDivider as BundledCardDivider } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const CardDivider = makeWrappedComponent(
	BundledCardDivider,
	'CardDivider'
);
