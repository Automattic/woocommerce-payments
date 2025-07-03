/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { Icon as BundledIcon } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const Icon = makeWrappedComponent( BundledIcon, 'Icon' );
