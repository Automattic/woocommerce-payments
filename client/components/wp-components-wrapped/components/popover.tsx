/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { Popover as BundledPopover } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const Popover = makeWrappedComponent( BundledPopover, 'Popover' );
