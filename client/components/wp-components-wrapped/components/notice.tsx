/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { Notice as BundledNotice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const Notice = makeWrappedComponent( BundledNotice, 'Notice' );
