/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { MenuItem as BundledMenuItem } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const MenuItem = makeWrappedComponent( BundledMenuItem, 'MenuItem' );
