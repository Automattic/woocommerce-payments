/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { Spinner as BundledSpinner } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const Spinner = makeWrappedComponent( BundledSpinner, 'Spinner' );
