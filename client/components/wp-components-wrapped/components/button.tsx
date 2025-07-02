/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { Button as BundledButton } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const Button = makeWrappedComponent( BundledButton, 'Button' );
