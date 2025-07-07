/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { DropZone as BundledDropZone } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const DropZone = makeWrappedComponent( BundledDropZone, 'DropZone' );
