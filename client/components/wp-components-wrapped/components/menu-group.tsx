/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { MenuGroup as BundledMenuGroup } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const MenuGroup = makeWrappedComponent( BundledMenuGroup, 'MenuGroup' );
