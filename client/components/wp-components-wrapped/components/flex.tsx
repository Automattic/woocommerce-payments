/**
 * External dependencies
 */
// eslint-disable-next-line no-restricted-syntax
import { Flex as BundledFlex } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { makeWrappedComponent } from '../make-wrapped-component';

export const Flex = makeWrappedComponent( BundledFlex, 'Flex' );
